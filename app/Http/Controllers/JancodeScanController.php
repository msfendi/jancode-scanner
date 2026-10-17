<?php

namespace App\Http\Controllers;

use App\Models\ImportBatch;
use App\Models\Jancode;
use App\Models\JancodeLogs;
use App\Models\ScanBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Scanner Jancode per PACKING LIST.
 *
 * Alur:
 * 1. Operator memilih packing list (revisi ACTIVE dari sebuah group).
 * 2. Sistem memuat seluruh jancode pada revisi aktif tersebut beserta balance.
 * 3. Setiap 1 scan = 1 carton. Barcode harus cocok dengan salah satu jancode
 *    pada packing list yang sedang aktif.
 * 4. Submit menyimpan scan sementara menjadi batch scan (BSCAN-...).
 */
class JancodeScanController extends Controller
{
    private const SESSION_KEY = 'scan_import_batch_id';

    // Halaman utama scanner
    public function index()
    {
        return view('jancode.scanner');
    }

    /**
     * Daftar packing list aktif untuk Select2.
     */
    public function packingLists(Request $request)
    {
        $q = $request->input('q');

        $query = ImportBatch::active()->orderByDesc('id');
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

        $results = $query->limit(30)->get()->map(function ($b) {
            return [
                'id'    => $b->id,
                'text'  => $b->summary_label,
                'label' => $b->group_label,
            ];
        });

        return response()->json($results);
    }

    /**
     * Muat packing list (revisi aktif) ke sesi scan.
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

        if (!$batch->is_active) {
            return response()->json([
                'success' => false,
                'error'   => 'not_active',
                'message' => 'Packing list ini bukan revisi aktif. Silakan pilih packing list terbaru.',
            ], 422);
        }

        $lockedId = session(self::SESSION_KEY);
        if ($lockedId && (int)$lockedId !== (int)$batchId) {
            $unsubmitted = $this->unsubmittedCount((int)$lockedId);
            if ($unsubmitted > 0) {
                return response()->json([
                    'success' => false,
                    'error'   => 'locked',
                    'message' => "Masih ada {$unsubmitted} scan sementara pada packing list sebelumnya. Submit atau rollback terlebih dahulu.",
                ], 403);
            }
        }

        session([self::SESSION_KEY => (int)$batchId]);

        return response()->json($this->batchState($batch) + [
            'success' => true,
            'message' => 'Packing list dimuat.',
        ]);
    }

    /**
     * Refresh data baris packing list.
     */
    public function rows(Request $request)
    {
        $batch = $this->resolveSessionBatch();
        if (!$batch) {
            return response()->json(['success' => false, 'message' => 'Pilih packing list terlebih dahulu'], 400);
        }

        return response()->json($this->batchState($batch) + ['success' => true]);
    }

    // POST scan — 1 scan = 1 carton
    public function scan(Request $request)
    {
        $barcode = preg_replace('/[^0-9]/', '', (string)$request->input('jancode'));

        if ($barcode === '') {
            return response()->json(['success' => false, 'message' => 'Barcode tidak boleh kosong'], 400);
        }

        $batchId = session(self::SESSION_KEY);
        if (!$batchId) {
            return response()->json([
                'success' => false,
                'error'   => 'no_packing_list',
                'message' => 'Pilih packing list terlebih dahulu sebelum scan.',
            ], 400);
        }

        $batch = ImportBatch::find($batchId);
        if (!$batch || !$batch->is_active) {
            session()->forget(self::SESSION_KEY);
            return response()->json([
                'success' => false,
                'error'   => 'not_active',
                'message' => 'Packing list yang dipilih sudah tidak aktif (ada revisi baru). Silakan muat ulang.',
            ], 409);
        }

        $count = max(1, (int)$request->input('count', 1));

        try {
            DB::beginTransaction();

            $master = Jancode::where('import_batch_id', $batchId)
                ->where('jancode', $barcode)
                ->where('void', 'false')
                ->lockForUpdate()
                ->first();

            if (!$master) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'error'   => 'not_found',
                    'message' => 'Barcode tidak ada pada packing list ini.',
                ], 404);
            }

            $totalAllCount = JancodeLogs::where('jancode_id', $master->id)->count();
            $capacity = (int)$master->packing_list_qty_carton;

            if ($totalAllCount + $count > $capacity) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'error'   => 'over',
                    'message' => 'Qty karton untuk jancode ini sudah terpenuhi (sisa ' . max(0, $capacity - $totalAllCount) . ' karton).',
                ], 422);
            }

            $logsToInsert = [];
            $now = now();
            for ($i = 0; $i < $count; $i++) {
                $logsToInsert[] = [
                    'jancode'      => $barcode,
                    'jancode_id'   => $master->id,
                    'user_id'      => auth()->id(),
                    'is_submitted' => 'false',
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ];
            }
            JancodeLogs::insert($logsToInsert);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem saat menyimpan data',
                'error'   => $e->getMessage(),
            ], 500);
        }

        $state = $this->batchState($batch);

        return response()->json($state + [
            'success' => true,
            'scanned_barcode' => $barcode,
            'jancode_id' => $master->id,
        ]);
    }

    // GET count — refresh counters
    public function count(Request $request)
    {
        $batch = $this->resolveSessionBatch();
        if (!$batch) {
            return response()->json(['scanned' => 0, 'unsubmitted' => 0, 'total_all' => 0, 'qty' => 0]);
        }

        return response()->json($this->batchState($batch));
    }

    /**
     * Submit seluruh scan sementara pada packing list menjadi 1 batch scan.
     */
    public function submit(Request $request)
    {
        $batchId = session(self::SESSION_KEY);
        if (!$batchId) {
            return response()->json(['success' => false, 'message' => 'Pilih packing list terlebih dahulu'], 400);
        }

        $batch = ImportBatch::find($batchId);
        if (!$batch) {
            return response()->json(['success' => false, 'message' => 'Packing list tidak ditemukan'], 404);
        }

        try {
            DB::beginTransaction();

            $jancodes = Jancode::where('import_batch_id', $batchId)->where('void', 'false')->lockForUpdate()->get();
            $jancodeIds = $jancodes->pluck('id')->all();

            $unsubmittedCount = JancodeLogs::whereIn('jancode_id', $jancodeIds)
                ->where('is_submitted', 'false')
                ->count();

            if ($unsubmittedCount <= 0) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Tidak ada scan sementara untuk disubmit'], 400);
            }

            // Generate Batch Number unik: BSCAN-YYYYMMDD-XXXX
            $today  = date('Ymd');
            $prefix = "BSCAN-{$today}-";
            $lastBatch = ScanBatch::where('batch_no', 'like', "{$prefix}%")->orderByDesc('id')->first();
            $nextSeq = $lastBatch ? ((int)substr($lastBatch->batch_no, strlen($prefix)) + 1) : 1;
            $batchNo = $prefix . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);

            $totalCarton = (int)$jancodes->sum('packing_list_qty_carton');

            // Simpan batch scan (per packing list)
            $scanBatch = ScanBatch::create([
                'batch_no'        => $batchNo,
                'jancode_id'      => null,
                'import_batch_id' => $batchId,
                'user_id'         => auth()->id(),
                'scanned_qty'     => $unsubmittedCount,
                'receive_qty'     => 0,
                'balance'         => 0,
                'total_qty'       => $totalCarton,
                'notes'           => "Batch scan {$unsubmittedCount} karton pada {$batch->group_label} oleh " . (auth()->user()?->name ?? 'Operator'),
            ]);

            // Tandai log tersubmit
            JancodeLogs::whereIn('jancode_id', $jancodeIds)
                ->where('is_submitted', 'false')
                ->update([
                    'is_submitted'  => 'true',
                    'user_id'       => auth()->id(),
                    'scan_batch_id' => $scanBatch->id,
                ]);

            // Update akumulasi receive & balance per jancode
            $receiveTotal = 0;
            foreach ($jancodes as $j) {
                $received = JancodeLogs::where('jancode_id', $j->id)->where('is_submitted', 'true')->count();
                $packing  = (int)$j->packing_list_qty_carton;
                $j->update([
                    'receive_qty_carton' => $received,
                    'balance'            => max(0, $packing - $received),
                ]);
                $receiveTotal += $received;
            }

            $scanBatch->update([
                'receive_qty' => $receiveTotal,
                'balance'     => max(0, $totalCarton - $receiveTotal),
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem saat submit batch scan: ' . $e->getMessage(),
                'error'   => $e->getMessage(),
            ], 500);
        }

        $state = $this->batchState($batch);

        return response()->json($state + [
            'success'           => true,
            'message'           => "Batch scan {$batchNo} berhasil disubmit ({$unsubmittedCount} karton).",
            'batch_no'          => $batchNo,
            'batch_id'          => $scanBatch->id,
            'batch_scanned_qty' => $unsubmittedCount,
        ]);
    }

    /**
     * Riwayat Batch Scan.
     */
    public function batchHistory(Request $request)
    {
        if ($request->ajax()) {
            $importBatchId = $request->input('import_batch_id');

            $query = ScanBatch::with(['jancode', 'user', 'importBatch'])->latest();
            if ($importBatchId) {
                $query->where('import_batch_id', $importBatchId);
            }

            $batches = $query->get()->map(function ($b) {
                $j  = $b->jancode;
                $ib = $b->importBatch;
                return [
                    'id'          => $b->id,
                    'batch_no'    => $b->batch_no,
                    'created_at'  => $b->created_at ? $b->created_at->format('d/m/Y H:i:s') : '-',
                    'packing_list'=> $ib ? ($ib->group_label . ' (' . strtoupper($ib->doc_type) . ' Rev.' . $ib->revision_no . ')') : '-',
                    'jancode'     => $j ? $j->jancode : '-',
                    'color'       => $j ? $j->color : '-',
                    'size'        => $j ? $j->size : '-',
                    'style'       => $j ? $j->style : ($ib->style_number ?? '-'),
                    'country'     => $j ? ($j->country ?: $j->destination) : ($ib->destination ?? '-'),
                    'scanned_qty' => $b->scanned_qty,
                    'receive_qty' => $b->receive_qty,
                    'balance'     => $b->balance,
                    'total_qty'   => $b->total_qty,
                    'user_name'   => $b->user ? $b->user->name : 'Operator',
                    'notes'       => $b->notes ?: '-',
                ];
            });

            return response()->json(['data' => $batches]);
        }

        return view('jancode.scan-batches');
    }

    // DELETE — void scan terakhir
    public function voidLast(Request $request)
    {
        $batch = $this->resolveSessionBatch();
        if (!$batch) {
            return response()->json(['success' => false, 'error' => 'Pilih packing list terlebih dahulu'], 400);
        }

        try {
            DB::beginTransaction();

            $jancodeIds = Jancode::where('import_batch_id', $batch->id)->pluck('id')->all();

            $last = JancodeLogs::whereIn('jancode_id', $jancodeIds)
                ->where('is_submitted', 'false')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->first();

            if (!$last) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'error' => 'Tidak ada log scan terakhir yang bisa dibatalkan',
                ], 404);
            }

            $last->delete();
            DB::commit();

            return response()->json($this->batchState($batch) + ['success' => true]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function rollback(Request $request)
    {
        $batch = $this->resolveSessionBatch();
        if (!$batch) {
            return response()->json(['success' => false, 'message' => 'Pilih packing list terlebih dahulu'], 400);
        }

        try {
            DB::beginTransaction();

            $jancodeIds = Jancode::where('import_batch_id', $batch->id)->pluck('id')->all();

            $deletedRows = JancodeLogs::whereIn('jancode_id', $jancodeIds)
                ->where('is_submitted', 'false')
                ->delete();

            if ($deletedRows <= 0) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'error' => 'Tidak ada scan sementara yang bisa di-rollback',
                ], 404);
            }

            DB::commit();

            return response()->json($this->batchState($batch) + [
                'success' => true,
                'message' => "{$deletedRows} scan sementara berhasil dihapus.",
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem saat rollback scan',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function resetLock(Request $request)
    {
        session()->forget(self::SESSION_KEY);
        return response()->json(['success' => true]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function resolveSessionBatch(): ?ImportBatch
    {
        $batchId = session(self::SESSION_KEY);
        if (!$batchId) {
            return null;
        }

        return ImportBatch::find($batchId);
    }

    private function unsubmittedCount(int $batchId): int
    {
        $jancodeIds = Jancode::where('import_batch_id', $batchId)->pluck('id')->all();
        if (empty($jancodeIds)) {
            return 0;
        }

        return JancodeLogs::whereIn('jancode_id', $jancodeIds)->where('is_submitted', 'false')->count();
    }

    /**
     * State lengkap packing list: info header, counter, dan baris jancode.
     */
    private function batchState(ImportBatch $batch): array
    {
        $jancodes = Jancode::where('import_batch_id', $batch->id)
            ->orderByRaw('carton_no_from IS NULL, carton_no_from ASC')
            ->orderBy('id')
            ->get();

        $jancodeIds = $jancodes->pluck('id')->all();

        $unsubmittedMap = empty($jancodeIds)
            ? collect()
            : JancodeLogs::whereIn('jancode_id', $jancodeIds)
                ->where('is_submitted', 'false')
                ->selectRaw('jancode_id, COUNT(*) as total')
                ->groupBy('jancode_id')
                ->pluck('total', 'jancode_id');

        $rows = [];
        $totalCarton = 0;
        $scannedTotal = 0;
        $unsubmittedTotal = 0;
        $completedRows = 0;

        foreach ($jancodes as $j) {
            $packing     = (int)$j->packing_list_qty_carton;
            $received    = (int)$j->receive_qty_carton;
            $unsubmitted = (int)($unsubmittedMap[$j->id] ?? 0);
            $balance     = max(0, $packing - $received - $unsubmitted);

            $totalCarton      += $packing;
            $scannedTotal     += $received;
            $unsubmittedTotal += $unsubmitted;

            $isComplete = $balance === 0;
            if ($isComplete) {
                $completedRows++;
            }

            $rows[] = [
                'id'               => $j->id,
                'jancode'          => $j->jancode,
                'color'            => $j->color,
                'size'             => $j->size,
                'carton_no'        => $j->carton_no_from
                    ? ($j->carton_no_to && $j->carton_no_to !== $j->carton_no_from ? "{$j->carton_no_from}-{$j->carton_no_to}" : (string)$j->carton_no_from)
                    : '-',
                'qty_per_carton'   => (int)$j->qty_per_carton,
                'packing'          => $packing,
                'receive'          => $received,
                'unsubmitted'      => $unsubmitted,
                'balance'          => $balance,
                'is_complete'      => $isComplete,
                'void'             => $j->void,
            ];
        }

        return [
            'packing_list' => [
                'id'          => $batch->id,
                'batch_number'=> $batch->batch_number,
                'doc_type'    => $batch->doc_type ?: 'proforma',
                'group_label' => $batch->group_label,
                'style'       => $batch->style_number,
                'ocf'         => $batch->ocf_number,
                'destination' => $batch->destination,
                'warehouse'   => $batch->warehouse,
                'buyer'       => $batch->supplier_name,
                'revision_no' => $batch->revision_no ?: 1,
                'is_active'   => (bool)$batch->is_active,
                'import_date' => $batch->import_date ? $batch->import_date->format('d/m/Y') : '-',
            ],
            'counters' => [
                'scanned'     => $scannedTotal,
                'unsubmitted' => $unsubmittedTotal,
                'total'       => $totalCarton,
                'balance'     => max(0, $totalCarton - $scannedTotal - $unsubmittedTotal),
                'rows_total'  => $rows === [] ? 0 : count($rows),
                'rows_done'   => $completedRows,
                'is_complete' => ($totalCarton > 0 && ($scannedTotal + $unsubmittedTotal) >= $totalCarton),
            ],
            'rows' => $rows,
        ];
    }

    /**
     * Pencarian jancode (dipertahankan untuk kompatibilitas).
     */
    public function search(Request $request)
    {
        $q = $request->input('q');
        $query = Jancode::where('void', 'false');

        if ($q) {
            $query->where(function ($w) use ($q) {
                $w->where('jancode', 'LIKE', "%$q%")
                  ->orWhere('size', 'LIKE', "%$q%")
                  ->orWhere('buyer', 'LIKE', "%$q%")
                  ->orWhere('destination', 'LIKE', "%$q%")
                  ->orWhere('style', 'LIKE', "%$q%");
            });
        }

        $targets = $query->limit(20)->get();
        $results = [];

        foreach ($targets as $target) {
            $totalAll    = JancodeLogs::where('jancode_id', $target->id)->count();
            $scanned     = JancodeLogs::where('jancode_id', $target->id)->where('is_submitted', 'true')->count();
            $unsubmitted = JancodeLogs::where('jancode_id', $target->id)->where('is_submitted', 'false')->count();
            $balance     = (int)($target->packing_list_qty_carton ?: $target->qty) - $totalAll;

            $results[] = [
                'id'          => $target->id,
                'text'        => "{$target->jancode} | Size: {$target->size} | Qty: " . ($target->packing_list_qty_carton ?: $target->qty) . " (Sisa: {$balance})",
                'jancode'     => $target->jancode,
                'size'        => $target->size,
                'qty'         => $target->packing_list_qty_carton ?: $target->qty,
                'scanned'     => $scanned,
                'unsubmitted' => $unsubmitted,
                'total_all'   => $totalAll,
                'balance'     => $balance,
            ];
        }

        return response()->json($results);
    }
}
