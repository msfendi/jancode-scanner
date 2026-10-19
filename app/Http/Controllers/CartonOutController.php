<?php

namespace App\Http\Controllers;

use App\Models\CartonOut;
use App\Models\CartonOutDetail;
use App\Models\ImportBatch;
use App\Models\ImportSession;
use App\Models\Jancode;
use App\Models\JancodeLogs;
use App\Models\PalletAddress;
use App\Models\PalletAssignmentDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * KARTON KELUAR (scan out).
 *
 * Karton yang sudah discan (receive) atau bahkan sudah ditempatkan di pallet
 * bisa diambil kembali oleh packing dalam satu packing list (revisi aktif).
 *
 * Aturan:
 * - Log scan tidak dihapus, hanya ditandai status = 'out' (soft) supaya riwayat
 *   tetap utuh. Semua perhitungan receive hanya menghitung status = 'in'.
 * - Operator memilih pallet asal secara manual bila karton sudah ditempatkan.
 * - Setiap pengambilan disimpan sebagai 1 batch BOUT-YYYYMMDD-0001 beserta
 *   rincian color/size/jancode/qty + lokasi pallet asal.
 */
class CartonOutController extends Controller
{
    private const SESSION_KEY = 'carton_out_import_batch_id';

    // Halaman utama Karton Keluar
    public function index()
    {
        return view('jancode.carton-out', [
            'currentSession' => ImportSession::current(),
        ]);
    }

    /**
     * Daftar packing list aktif (revisi aktif session) untuk Select2.
     */
    public function packingLists(Request $request)
    {
        $q = $request->input('q');

        $query = ImportBatch::active()
            ->where('session_id', ImportSession::currentId() ?? 0)
            ->orderByDesc('id');

        if ($q) {
            $query->where(function ($w) use ($q) {
                $w->where('group_label', 'LIKE', "%$q%")
                  ->orWhere('batch_number', 'LIKE', "%$q%")
                  ->orWhere('style_number', 'LIKE', "%$q%")
                  ->orWhere('ocf_number', 'LIKE', "%$q%")
                  ->orWhere('destination', 'LIKE', "%$q%")
                  ->orWhere('warehouse', 'LIKE', "%$q%");
            });
        }

        return response()->json($query->limit(30)->get()->map(function ($b) {
            return [
                'id'    => $b->id,
                'text'  => $b->summary_label,
                'label' => $b->group_label,
            ];
        }));
    }

    /**
     * Muat packing list (revisi aktif) ke sesi karton keluar.
     */
    public function load(Request $request)
    {
        $batchId = $request->input('import_batch_id');
        if (!$batchId) {
            return response()->json(['success' => false, 'message' => 'Packing list tidak boleh kosong'], 400);
        }

        $batch = ImportBatch::find($batchId);
        if (!$batch) {
            return response()->json(['success' => false, 'message' => 'Packing list tidak ditemukan'], 404);
        }

        $currentSession = ImportSession::current();
        if (!$currentSession) {
            return response()->json([
                'success' => false,
                'error'   => 'no_session',
                'message' => 'Mulai session terlebih dahulu sebelum scan out.',
            ], 422);
        }

        if (!$currentSession->isOpen()) {
            return response()->json([
                'success' => false,
                'error'   => 'session_closed',
                'message' => 'Session ini sudah ditutup. Buat session baru untuk scan out.',
            ], 422);
        }

        if ((int) $batch->session_id !== (int) $currentSession->id) {
            return response()->json([
                'success' => false,
                'error'   => 'wrong_session',
                'message' => 'Packing list ini bukan milik session aktif.',
            ], 422);
        }

        if (!$batch->is_active) {
            return response()->json([
                'success' => false,
                'error'   => 'not_active',
                'message' => 'Packing list ini bukan revisi aktif. Silakan pilih packing list terbaru.',
            ], 422);
        }

        session([self::SESSION_KEY => (int) $batchId]);

        return response()->json($this->state($batch) + [
            'success' => true,
            'message' => 'Packing list dimuat.',
        ]);
    }

    /**
     * Refresh data baris packing list.
     */
    public function rows(Request $request)
    {
        $batch = $this->resolveBatch();
        if (!$batch) {
            return response()->json(['success' => false, 'message' => 'Pilih packing list terlebih dahulu'], 400);
        }

        return response()->json($this->state($batch) + ['success' => true]);
    }

    /**
     * Cari 1 baris berdasarkan barcode JAN (untuk input scan).
     */
    public function lookup(Request $request)
    {
        $batch = $this->resolveBatch();
        if (!$batch) {
            return response()->json([
                'success' => false,
                'error'   => 'no_packing_list',
                'message' => 'Pilih packing list terlebih dahulu.',
            ], 400);
        }

        $barcode = preg_replace('/[^0-9]/', '', (string) $request->input('jancode'));
        if ($barcode === '') {
            return response()->json(['success' => false, 'message' => 'Barcode tidak boleh kosong'], 400);
        }

        $row = $this->rowsPayload($batch)->firstWhere('jancode', $barcode);
        if (!$row) {
            return response()->json([
                'success' => false,
                'error'   => 'not_found',
                'message' => 'Barcode tidak ada pada packing list ini.',
            ], 404);
        }

        if ((int) $row['receive'] <= 0) {
            return response()->json([
                'success' => false,
                'error'   => 'no_receive',
                'message' => 'Karton ini belum pernah discan (belum ada receive).',
            ], 422);
        }

        return response()->json(['success' => true, 'row' => $row]);
    }

    /**
     * Simpan batch KARTON KELUAR.
     */
    public function store(Request $request)
    {
        $request->validate([
            'import_batch_id'           => 'required|integer',
            'notes'                     => 'nullable|string|max:500',
            'items'                     => 'required|array|min:1',
            'items.*.jancode_id'        => 'required|integer',
            'items.*.qty_carton'        => 'required|integer|min:1',
            'items.*.pallet_address_id' => 'nullable|integer',
        ]);

        $batch = ImportBatch::find($request->input('import_batch_id'));
        if (!$batch) {
            return response()->json(['success' => false, 'message' => 'Packing list tidak ditemukan'], 404);
        }

        $currentSession = ImportSession::current();
        if (!$currentSession || (int) $batch->session_id !== (int) $currentSession->id) {
            return response()->json([
                'success' => false,
                'message' => 'Packing list ini bukan milik session aktif.',
            ], 403);
        }

        if (!$currentSession->isOpen()) {
            return response()->json([
                'success' => false,
                'message' => 'Session ini sudah ditutup. Buat session baru untuk scan out.',
            ], 422);
        }

        if (!$batch->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Packing list ini bukan revisi aktif. Silakan pilih packing list terbaru.',
            ], 422);
        }

        try {
            $out = DB::transaction(function () use ($batch, $request) {
                $items = $request->input('items');

                // Kebutuhan per jancode & per (jancode, pallet).
                $need = [];
                $palletNeed = [];
                $unplacedNeed = [];

                foreach ($items as $it) {
                    $jid  = (int) $it['jancode_id'];
                    $qty  = (int) $it['qty_carton'];
                    $addr = !empty($it['pallet_address_id']) ? (int) $it['pallet_address_id'] : null;

                    $need[$jid] = ($need[$jid] ?? 0) + $qty;

                    if ($addr) {
                        $palletNeed["{$jid}|{$addr}"] = ($palletNeed["{$jid}|{$addr}"] ?? 0) + $qty;
                    } else {
                        $unplacedNeed[$jid] = ($unplacedNeed[$jid] ?? 0) + $qty;
                    }
                }

                $jancodes = Jancode::where('import_batch_id', $batch->id)
                    ->whereIn('id', array_keys($need))
                    ->where('void', 'false')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $ids       = array_values(array_unique(array_map('intval', array_keys($need))));
                $inMap     = $this->logCounts($ids, JancodeLogs::STATUS_IN);
                $placedMap = $this->placedCounts($ids);

                // 1. Validasi receive tersedia per jancode.
                foreach ($need as $jid => $qty) {
                    $j = $jancodes[$jid] ?? null;
                    if (!$j) {
                        throw new \RuntimeException('Ada baris jancode yang tidak ditemukan pada packing list ini.');
                    }
                    $available = (int) ($inMap[$jid] ?? 0);
                    if ($qty > $available) {
                        throw new \RuntimeException("Qty keluar untuk {$j->jancode} ({$qty}) melebihi receive tersedia ({$available} karton).");
                    }
                }

                // 2. Validasi isi pallet asal.
                foreach ($palletNeed as $key => $qty) {
                    [$jid, $addr] = array_map('intval', explode('|', $key));
                    $detail = PalletAssignmentDetail::where('jancode_id', $jid)
                        ->whereHas('assignment', fn($q) => $q->where('pallet_address_id', $addr))
                        ->lockForUpdate()
                        ->first();

                    $have = $detail ? (int) $detail->qty_carton : 0;
                    if ($qty > $have) {
                        $code = PalletAddress::find($addr)?->code ?? $addr;
                        throw new \RuntimeException("Qty dari pallet {$code} ({$qty}) melebihi isi pallet untuk jancode ini ({$have} karton).");
                    }
                }

                // 3. Validasi karton yang belum ditempatkan.
                foreach ($unplacedNeed as $jid => $qty) {
                    $j       = $jancodes[$jid];
                    $receive = (int) ($inMap[$jid] ?? 0);
                    $placed  = (int) ($placedMap[$jid] ?? 0);
                    $free    = max(0, $receive - $placed);
                    if ($qty > $free) {
                        throw new \RuntimeException("Qty tanpa pallet untuk {$j->jancode} ({$qty}) melebihi karton yang belum ditempatkan ({$free} karton).");
                    }
                }

                // 4. Header batch keluar.
                $out = CartonOut::create([
                    'code'            => CartonOut::generateCode(),
                    'session_id'      => $batch->session_id,
                    'packing_list_id' => $batch->packing_list_id,
                    'import_batch_id' => $batch->id,
                    'user_id'         => auth()->id(),
                    'total_carton'    => array_sum($need),
                    'notes'           => $request->input('notes'),
                ]);

                // 5. Kurangi pallet + simpan rincian.
                foreach ($items as $it) {
                    $jid  = (int) $it['jancode_id'];
                    $qty  = (int) $it['qty_carton'];
                    $j    = $jancodes[$jid];
                    $addr = !empty($it['pallet_address_id']) ? (int) $it['pallet_address_id'] : null;
                    $code = null;

                    if ($addr) {
                        $detail = PalletAssignmentDetail::where('jancode_id', $jid)
                            ->whereHas('assignment', fn($q) => $q->where('pallet_address_id', $addr))
                            ->lockForUpdate()
                            ->first();

                        if (!$detail) {
                            throw new \RuntimeException("Detail pallet untuk {$j->jancode} tidak ditemukan (mungkin sudah berubah). Silakan muat ulang.");
                        }

                        $assignment = $detail->assignment;
                        $code       = $assignment?->address?->code ?? PalletAddress::find($addr)?->code;

                        if ((int) $detail->qty_carton === $qty) {
                            $detail->delete();
                        } else {
                            $detail->decrement('qty_carton', $qty);
                        }

                        if ($assignment) {
                            $assignment->decrement('total_carton', $qty);
                            if ((int) $assignment->total_carton <= 0) {
                                $assignment->delete();
                            }
                        }
                    }

                    CartonOutDetail::create([
                        'carton_out_id'     => $out->id,
                        'jancode_id'        => $jid,
                        'jancode'           => $j->jancode,
                        'color'             => $j->color,
                        'size'              => $j->size,
                        'qty_carton'        => $qty,
                        'pallet_address_id' => $addr,
                        'pallet_code'       => $code,
                    ]);
                }

                // 6. Tandai log scan sebagai 'out' (soft) — receive otomatis berkurang.
                foreach ($need as $jid => $qty) {
                    $logIds = JancodeLogs::where('jancode_id', $jid)
                        ->where('is_submitted', 'true')
                        ->inStock()
                        ->orderByDesc('id')
                        ->limit($qty)
                        ->pluck('id')
                        ->all();

                    if (count($logIds) < $qty) {
                        $jan = $jancodes[$jid]->jancode ?? $jid;
                        throw new \RuntimeException("Jumlah log scan untuk {$jan} tidak cukup untuk dikeluarkan.");
                    }

                    JancodeLogs::whereIn('id', $logIds)->update([
                        'status'        => JancodeLogs::STATUS_OUT,
                        'carton_out_id' => $out->id,
                        'out_at'        => now(),
                    ]);
                }

                // 7. Hitung ulang receive & balance.
                foreach ($jancodes as $j) {
                    $received = JancodeLogs::where('jancode_id', $j->id)
                        ->where('is_submitted', 'true')
                        ->inStock()
                        ->count();
                    $packing = (int) $j->packing_list_qty_carton;

                    $j->update([
                        'receive_qty_carton' => $received,
                        'balance'            => max(0, $packing - $received),
                    ]);
                }

                return $out;
            });
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyimpan karton keluar: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json($this->state($batch) + [
            'success' => true,
            'message' => "Karton keluar {$out->code} berhasil disimpan ({$out->total_carton} karton).",
            'code'    => $out->code,
        ]);
    }

    /**
     * Riwayat Karton Keluar (DataTable).
     */
    public function history(Request $request)
    {
        if (!$request->ajax()) {
            return redirect()->route('cartonOut.index');
        }

        $query = CartonOut::with(['details', 'user', 'importBatch', 'session'])->latest();

        $sessionId = ImportSession::currentId();
        if ($sessionId) {
            $query->where('session_id', $sessionId);
        }

        if ($request->filled('import_batch_id')) {
            $query->where('import_batch_id', $request->input('import_batch_id'));
        }

        $data = [];
        foreach ($query->get() as $out) {
            foreach ($out->details as $d) {
                $data[] = [
                    'code'         => $out->code,
                    'created_at'   => $out->created_at ? $out->created_at->format('d/m/Y H:i:s') : '-',
                    'session_code' => $out->session?->code ?: '-',
                    'packing_list' => $out->importBatch?->group_label ?: '-',
                    'user_name'    => $out->user?->name ?: 'Operator',
                    'jancode'      => $d->jancode,
                    'color'        => $d->color ?: '-',
                    'size'         => $d->size ?: '-',
                    'qty_carton'   => (int) $d->qty_carton,
                    'pallet_code'  => $d->pallet_code ?: 'Belum di pallet',
                    'notes'        => $out->notes ?: '-',
                ];
            }
        }

        return response()->json(['data' => $data]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function resolveBatch(): ?ImportBatch
    {
        $batchId = session(self::SESSION_KEY);
        if (!$batchId) {
            return null;
        }

        $batch = ImportBatch::find($batchId);
        if (!$batch) {
            return null;
        }

        $sessionId = ImportSession::currentId();
        if (!$sessionId || (int) $batch->session_id !== (int) $sessionId) {
            return null;
        }

        return $batch;
    }

    /**
     * State lengkap packing list: header + baris (receive/out/placed/pallet) + total.
     */
    private function state(ImportBatch $batch): array
    {
        $rows = $this->rowsPayload($batch);

        return [
            'packing_list' => [
                'id'          => $batch->id,
                'packing_list_id' => $batch->packing_list_id,
                'group_label' => $batch->group_label,
                'doc_type'    => strtoupper($batch->doc_type ?: 'proforma'),
                'revision_no' => $batch->revision_no ?: 1,
                'style'       => $batch->style_number,
                'ocf'         => $batch->ocf_number,
                'destination' => $batch->destination,
                'warehouse'   => $batch->warehouse,
                'is_active'   => (bool) $batch->is_active,
            ],
            'totals' => [
                'receive' => (int) $rows->sum('receive'),
                'out'     => (int) $rows->sum('out'),
                'placed'  => (int) $rows->sum('placed'),
            ],
            'rows' => $rows,
        ];
    }

    /**
     * Baris jancode beserta angka receive/out/placed dan daftar pallet asal.
     */
    private function rowsPayload(ImportBatch $batch)
    {
        $jancodes = Jancode::where('import_batch_id', $batch->id)
            ->where('void', 'false')
            ->orderByRaw('carton_no_from IS NULL, carton_no_from ASC')
            ->orderBy('id')
            ->get();

        $ids = $jancodes->pluck('id')->all();

        $inMap     = $this->logCounts($ids, JancodeLogs::STATUS_IN);
        $outMap    = $this->logCounts($ids, JancodeLogs::STATUS_OUT);
        $placedMap = $this->placedCounts($ids);
        $palletMap = $this->palletMap($ids);

        return $jancodes->map(function ($j) use ($inMap, $outMap, $placedMap, $palletMap) {
            $receive = (int) ($inMap[$j->id] ?? 0);
            $placed  = (int) ($placedMap[$j->id] ?? 0);

            return [
                'id'        => $j->id,
                'jancode'   => $j->jancode,
                'color'     => $j->color,
                'size'      => $j->size,
                'carton_no' => $j->carton_no_from
                    ? ($j->carton_no_to && $j->carton_no_to !== $j->carton_no_from ? "{$j->carton_no_from}-{$j->carton_no_to}" : (string) $j->carton_no_from)
                    : '-',
                'packing'   => (int) $j->packing_list_qty_carton,
                'receive'   => $receive,
                'out'       => (int) ($outMap[$j->id] ?? 0),
                'placed'    => $placed,
                'unplaced'  => max(0, $receive - $placed),
                'pallets'   => $palletMap[$j->id] ?? [],
            ];
        })->values();
    }

    /**
     * Hitung jumlah log per jancode untuk status tertentu (hanya yg tersubmit).
     */
    private function logCounts(array $jancodeIds, string $status)
    {
        if (empty($jancodeIds)) {
            return collect();
        }

        return JancodeLogs::whereIn('jancode_id', $jancodeIds)
            ->where('is_submitted', 'true')
            ->where('status', $status)
            ->selectRaw('jancode_id, COUNT(*) as total')
            ->groupBy('jancode_id')
            ->pluck('total', 'jancode_id');
    }

    /**
     * Total karton yang sudah ditempatkan di pallet per jancode.
     */
    private function placedCounts(array $jancodeIds)
    {
        if (empty($jancodeIds)) {
            return collect();
        }

        return PalletAssignmentDetail::whereIn('jancode_id', $jancodeIds)
            ->selectRaw('jancode_id, COALESCE(SUM(qty_carton), 0) as total')
            ->groupBy('jancode_id')
            ->pluck('total', 'jancode_id');
    }

    /**
     * Daftar pallet asal per jancode: [{address_id, code, qty}].
     */
    private function palletMap(array $jancodeIds): array
    {
        if (empty($jancodeIds)) {
            return [];
        }

        $details = PalletAssignmentDetail::whereIn('jancode_id', $jancodeIds)
            ->with('assignment.address')
            ->get();

        $map = [];
        foreach ($details as $d) {
            $assignment = $d->assignment;
            $address    = $assignment?->address;

            $map[$d->jancode_id][] = [
                'address_id' => $assignment?->pallet_address_id,
                'code'       => $address?->code ?? '-',
                'qty'        => (int) $d->qty_carton,
            ];
        }

        return $map;
    }
}
