<?php

namespace App\Http\Controllers;

use App\Exports\JancodeSheet\JancodeTemplateExport;
use App\Exports\JancodeSheet\ScanLogsExport;
use App\Models\CartonOut;
use App\Models\CartonOutDetail;
use App\Models\Jancode;
use App\Models\JancodeLogs;
use App\Models\ImportBatch;
use App\Models\ImportFailedRow;
use App\Models\ImportSession;
use App\Models\PackingList;
use App\Models\PalletAssignment;
use App\Models\PalletAssignmentDetail;
use App\Models\ScanBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use RealRashid\SweetAlert\Facades\Alert;

class JancodeController extends Controller
{
    /**
     * Halaman Import Packing List.
     *
     * Pintu masuk alur: operator mengupload file Excel packing list
     * (PROFORMA / ACTUAL). Setiap sheet disimpan sebagai satu revisi.
     */
    public function index(Request $request)
    {
        $currentSession = ImportSession::current();

        return view('jancode.index', compact('currentSession'));
    }

    public function scanLogs($id)
    {
        $jancode = Jancode::findOrFail($id);

        $logs = JancodeLogs::where('jancode_id', $jancode->id)
                    ->with('user')
                    ->orderBy('created_at', 'asc')
                    ->get()
                    ->map(function ($log, $index) {
                        return [
                            'no'         => $index + 1,
                            'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                            'user_name'  => $log->user ? $log->user->name : 'Unknown',
                            'status'     => $log->status ?: 'in',
                            'out_at'     => $log->out_at ? \Illuminate\Support\Carbon::parse($log->out_at)->format('Y-m-d H:i:s') : null,
                        ];
                    })
                    ->values();

        $total = $logs->count();

        // Batch scan yang menyentuh jancode ini (per-jancode lama, atau batch packing list baru)
        $batches = ScanBatch::where('jancode_id', $jancode->id)
            ->orWhere(function ($q) use ($jancode) {
                if ($jancode->import_batch_id) {
                    $q->where('import_batch_id', $jancode->import_batch_id);
                } else {
                    $q->whereRaw('1 = 0');
                }
            })
            ->with('user')
            ->latest()
            ->get()
            ->map(function ($b) {
                return [
                    'batch_no'    => $b->batch_no,
                    'scanned_qty' => $b->scanned_qty,
                    'receive_qty' => $b->receive_qty,
                    'balance'     => $b->balance,
                    'user_name'   => $b->user ? $b->user->name : 'Operator',
                    'created_at'  => $b->created_at ? $b->created_at->format('d/m/Y H:i') : '-',
                ];
            });

        return response()->json([
            'destination'  => $jancode->destination ?: $jancode->country,
            'buyer'        => $jancode->buyer,
            'style'        => $jancode->style,
            'cpo'          => $jancode->cpo ?: $jancode->ocf,
            'size'         => $jancode->size,
            'color'        => $jancode->color,
            'qty'          => (int)($jancode->packing_list_qty_carton ?: $jancode->qty),
            'total_scans'  => (int)($jancode->receive_qty_carton ?: $total),
            'balance'      => (int)$jancode->balance,
            'logs'         => $logs,
            'batches'      => $batches,
        ]);
    }

    public function exportScanLogs($id)
    {
        $jancode = Jancode::findOrFail($id);

        $filename = 'scan_log_' . $jancode->jancode . '_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new ScanLogsExport($jancode), $filename);
    }

    public function store(Request $request)
    {
        $request->validate([
            'jancode'     => 'required|string',
            'size'        => 'required|string',
            'qty'         => 'required|integer',
            'destination' => 'required|string',
            'buyer'       => 'required|string',
            'style'       => 'required|string',
            'cpo'         => 'required|string',
            'void'        => 'required|string'
        ]);

        Jancode::updateOrCreate(
            ['id' => $request->id],
            [
                'jancode'     => $request->jancode,
                'size'        => $request->size,
                'qty'         => $request->qty,
                'destination' => $request->destination,
                'buyer'       => $request->buyer,
                'style'       => $request->style,
                'cpo'         => $request->cpo,
                'void'        => $request->void ? 'false' : 'true',
            ]
        );

        return response()->json(['success' => 'Jancode saved successfully.']);
    }

    public function edit($id)
    {
        $jancode = Jancode::find($id);
        return response()->json($jancode);
    }

    public function destroy($id)
    {
        Jancode::find($id)->delete();
        return response()->json(['success' => 'Jancode deleted successfully.']);
    }

    /**
     * @deprecated Import lama (tanpa session/packing list) sudah tidak dipakai.
     * Gunakan alur: Start Session -> Import Packing List (aiPreview/aiConfirm).
     */
    public function import(Request $request)
    {
        return $this->legacyImportDisabledResponse();
    }

    /**
     * @deprecated Lihat import().
     */
    public function importNewFormat(Request $request)
    {
        return $this->legacyImportDisabledResponse();
    }

    private function legacyImportDisabledResponse()
    {
        return response()->json([
            'success' => false,
            'error'   => 'legacy_import_disabled',
            'message' => 'Import lama dinonaktifkan. Mulai Session lalu gunakan halaman Import Packing List agar data masuk ke packing list & session yang benar.',
        ], 410);
    }

    /**
     * Preview Import Packing List (PROFORMA / ACTUAL) untuk seluruh sheet.
     */
    public function previewAiImport(Request $request, \App\Services\JancodeImportService $service)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:15360'
        ]);

        $importSession = ImportSession::current();
        if (!$importSession) {
            return response()->json([
                'success' => false,
                'error'   => 'no_session',
                'message' => 'Mulai session terlebih dahulu sebelum import packing list.',
            ], 422);
        }

        if (!$importSession->isOpen()) {
            return response()->json([
                'success' => false,
                'error'   => 'session_closed',
                'message' => 'Session ini sudah ditutup. Buat session baru untuk import packing list.',
            ], 422);
        }

        try {
            $uploadedFile = $request->file('file');
            $originalName = $uploadedFile->getClientOriginalName();
            $preview = $service->parseWorkbook($uploadedFile->getRealPath());

            if (empty($preview['sheets'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak menemukan sheet packing list (judul "PACKING LIST ...") pada file ini.',
                ], 422);
            }

            // ROUTING: satu kombinasi style|OCF|destination|warehouse = satu
            // session. Satu file boleh memuat beberapa packing list (mis.
            // warehouse berbeda); setiap group diarahkan ke session-nya
            // masing-masing (reuse session yang ada, atau buat session baru).
            $plan = $this->planGroupSessions($preview['sheets'], $importSession, true);

            // Tandai duplikat + susun ringkasan routing per group.
            $routing = [];

            foreach ($preview['sheets'] as &$sheet) {
                $groupKey = ImportBatch::buildGroupKey($sheet['header']);
                $target   = $plan[$groupKey] ?? ['session' => null, 'is_new' => true];
                $targetId = $target['session']?->id;

                $active = $targetId ? ImportBatch::activeForGroup($groupKey, $targetId) : null;

                $existingKeys = [];
                if ($active) {
                    $existingKeys = Jancode::where('import_batch_id', $active->id)
                        ->get(['color', 'size', 'jancode'])
                        ->map(fn($i) => strtolower(trim("{$i->color}|{$i->size}|{$i->jancode}")))
                        ->flip()
                        ->toArray();
                }

                foreach ($sheet['items'] as &$item) {
                    $key = strtolower(trim("{$item['color']}|{$item['size']}|{$item['jan_code']}"));
                    $item['is_duplicate'] = isset($existingKeys[$key]);
                }
                unset($item);

                $sheet['group_key']           = $groupKey;
                $sheet['group_label']         = ImportBatch::buildGroupLabel($sheet['header']);
                $sheet['target_session_id']   = $targetId;
                $sheet['target_session_code'] = $target['session']?->code;
                $sheet['target_is_new']       = (bool) $target['is_new'];
                $sheet['next_revision']       = $targetId
                    ? ImportBatch::nextRevisionForGroup($groupKey, $targetId)
                    : 1;
                $sheet['active_batch_no']     = $active->batch_number ?? null;

                if (!isset($routing[$groupKey])) {
                    $routing[$groupKey] = [
                        'group_key'     => $groupKey,
                        'group_label'   => $sheet['group_label'],
                        'session_id'    => $targetId,
                        'session_code'  => $target['session']?->code,
                        'session_status'=> $target['session']?->status,
                        'is_new'        => (bool) $target['is_new'],
                        'next_revision' => $sheet['next_revision'],
                        'doc_types'     => [],
                        'total_items'   => 0,
                        'total_carton'  => 0,
                        'sheets'        => [],
                    ];
                }

                $routing[$groupKey]['doc_types'][strtoupper($sheet['doc_type'])] = true;
                $routing[$groupKey]['total_items']  += $sheet['total_items'];
                $routing[$groupKey]['total_carton'] += $sheet['total_carton'];
                $routing[$groupKey]['sheets'][]     = $sheet['sheet_name'];
            }
            unset($sheet);

            $routing = array_values(array_map(function (array $r) {
                $r['doc_types'] = implode(' + ', array_keys($r['doc_types']));
                $r['sheets']    = implode(', ', $r['sheets']);
                return $r;
            }, $routing));

            return response()->json([
                'success'          => true,
                'data'             => $preview,
                'original_filename'=> $originalName,
                'routing'          => $routing,
                'session'          => [
                    'id'          => $importSession->id,
                    'code'        => $importSession->code,
                    'group_key'   => $importSession->group_key,
                    'group_label' => $importSession->group_label,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Packing list import error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses file Excel: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * Konfirmasi & simpan import packing list.
     *
     * Setiap sheet menjadi satu REVISI packing list. Revisi terbaru pada group
     * (style + ocf/cpo + destination + warehouse) otomatis menjadi ACTIVE.
     */
    public function confirmAiImport(Request $request, \App\Services\PackingListImportService $importService)
    {
        $request->validate([
            'sheets'                          => 'nullable|array',
            'items'                           => 'nullable|array',
            'items.*.jan_code'                => 'nullable|string',
            'items.*.packing_list_qty_carton' => 'nullable|integer',
            'original_filename'               => 'nullable|string',
            'supplier_name'                   => 'nullable|string',
            'batch_name'                      => 'nullable|string',
        ]);

        $sheets = $this->normalizeImportSheets($request);

        if (empty($sheets)) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada baris data yang bisa diimport.',
            ], 422);
        }

        $importSession = ImportSession::current();
        if (!$importSession) {
            return response()->json([
                'success' => false,
                'error'   => 'no_session',
                'message' => 'Mulai session terlebih dahulu sebelum import packing list.',
            ], 422);
        }

        if (!$importSession->isOpen()) {
            return response()->json([
                'success' => false,
                'error'   => 'session_closed',
                'message' => 'Session ini sudah ditutup. Buat session baru untuk import packing list.',
            ], 422);
        }

        // ROUTING: arahkan setiap group ke session-nya (reuse bila sudah ada,
        // buat baru bila belum). Session yang sudah ditutup dibuka kembali.
        $plan = $this->planGroupSessions($sheets, $importSession, false);

        $created       = [];
        $importedCount = 0;
        $skippedCount  = 0;

        try {
            DB::transaction(function () use ($sheets, $plan, $request, $importService, &$created, &$importedCount, &$skippedCount) {
                foreach ($sheets as $sheet) {
                    $gk = ImportBatch::buildGroupKey($sheet['header'] ?? []);
                    $targetSession = $plan[$gk]['session'] ?? null;

                    if (!$targetSession) {
                        throw new \InvalidArgumentException('Session tujuan untuk packing list ini tidak dapat ditentukan.');
                    }

                    $meta = [
                        'session_id'        => $targetSession->id,
                        'original_filename' => $request->input('original_filename'),
                        'batch_name'        => $request->input('batch_name'),
                        'supplier_name'     => $request->input('supplier_name'),
                        'imported_by'       => auth()->id(),
                    ];

                    $result = $importService->importSheet($sheet, $meta);
                    if (!$result) {
                        continue;
                    }

                    /** @var ImportBatch $batch */
                    $batch = $result['batch'];

                    $created[] = [
                        'batch_id'     => $batch->id,
                        'batch_number' => $batch->batch_number,
                        'session_id'   => $targetSession->id,
                        'session_code' => $targetSession->code,
                        'group_label'  => $batch->group_label,
                        'doc_type'     => $batch->doc_type,
                        'revision_no'  => $batch->revision_no,
                        'imported'     => $result['imported'],
                        'skipped'      => $result['skipped'],
                        'total_carton' => $batch->total_carton,
                    ];

                    $importedCount += $result['imported'];
                    $skippedCount  += $result['skipped'];
                }
            });
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error'   => 'group_mismatch',
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Import packing list gagal: ' . $e->getMessage() . "\n" . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan import: ' . $e->getMessage(),
            ], 500);
        }

        if (empty($created)) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada baris valid yang bisa diimport (semua baris broken/invalid).',
            ], 422);
        }

        $sessionSummary = collect($plan)->map(fn ($p) => [
            'id'     => $p['session']->id,
            'code'   => $p['session']->code,
            'is_new' => (bool) $p['is_new'],
            'status' => $p['session']->status,
        ])->unique('id')->values();

        return response()->json([
            'success'      => true,
            'message'      => "Import berhasil: {$importedCount} baris valid pada " . count($created) . " packing list. {$skippedCount} baris broken disimpan terpisah.",
            'batches'      => $created,
            'sessions'     => $sessionSummary,
            'batch_number' => $created[0]['batch_number'],
            'batch_id'     => $created[0]['batch_id'],
            'failed_count' => $skippedCount,
        ]);
    }

    /**
     * Normalisasi payload import menjadi array sheet.
     */
    private function normalizeImportSheets(Request $request): array
    {
        $sheets = $request->input('sheets');
        if (is_array($sheets) && !empty($sheets)) {
            return $sheets;
        }

        // Backward-compatible: payload lama (items + skipped) tanpa sheets
        $items   = $request->input('items', []);
        $skipped = $request->input('skipped', []);
        if (empty($items) && empty($skipped)) {
            return [];
        }

        $first = $items[0] ?? ($skipped[0] ?? []);

        return [[
            'sheet_name' => $request->input('sheet_name'),
            'doc_type'   => $request->input('doc_type', 'proforma'),
            'header'     => [
                'buyer'     => $first['buyer'] ?? $request->input('supplier_name'),
                'style'     => $first['style'] ?? null,
                'country'   => $first['country'] ?? $first['destination'] ?? null,
                'ocf'       => $first['ocf'] ?? $first['cpo'] ?? null,
                'warehouse' => $first['warehouse'] ?? null,
            ],
            'items'      => $items,
            'skipped'    => $skipped,
        ]];
    }

    /**
     * Susun rencana routing group -> session.
     *
     * Aturan (satu kombinasi style|OCF|destination|warehouse = satu session):
     *  1. Jika kombinasi sudah dimiliki session lain, pakai session itu
     *     (dibuka kembali bila sedang ditutup).
     *  2. Jika session aktif masih kosong (baru di-start), pakai untuk group
     *     pertama pada file ini.
     *  3. Sisanya dibuatkan session baru khusus untuk kombinasi tersebut.
     *
     * @param  array<int, array>  $sheets
     * @return array<string, array{session: ?ImportSession, is_new: bool}>
     */
    private function planGroupSessions(array $sheets, ImportSession $current, bool $dryRun = false): array
    {
        $groups = [];
        foreach ($sheets as $sheet) {
            $groupKey = ImportBatch::buildGroupKey($sheet['header'] ?? []);
            if (!array_key_exists($groupKey, $groups)) {
                $groups[$groupKey] = $sheet['header'] ?? [];
            }
        }

        $plan        = [];
        $currentUsed = false;

        foreach ($groups as $groupKey => $header) {
            // 1. Session yang sudah memegang kombinasi ini.
            $owner = ImportSession::findByGroup($groupKey, null);

            if ($owner) {
                if (!$dryRun && $owner->isClosed()) {
                    $owner->reopen();
                }

                $plan[$groupKey] = ['session' => $owner, 'is_new' => false];

                if ((int) $owner->id === (int) $current->id) {
                    $currentUsed = true;
                }
                continue;
            }

            // 2. Session aktif yang masih kosong -> group pertama.
            if (!$currentUsed && $current->isOpen() && !$current->group_key) {
                $plan[$groupKey] = ['session' => $current, 'is_new' => false];
                $currentUsed     = true;
                continue;
            }

            // 3. Session baru untuk kombinasi ini.
            if ($dryRun) {
                $plan[$groupKey] = ['session' => null, 'is_new' => true];
                continue;
            }

            $plan[$groupKey] = [
                'session' => ImportSession::create([
                    'code'       => ImportSession::generateCode(),
                    'status'     => 'open',
                    'notes'      => 'Otomatis dibuat saat import: ' . ImportBatch::buildGroupLabel($header),
                    'user_id'    => auth()->id(),
                    'started_at' => now(),
                ]),
                'is_new' => true,
            ];
        }

        return $plan;
    }

    /**
     * Riwayat Import Batch (seluruh revisi).
     *
     * Sudah digantikan oleh halaman Manage Packing List: setiap packing list
     * menampilkan seluruh revisi beserta baris broken-nya pada detail revisi.
     * URL lama tetap dipertahankan sebagai redirect agar bookmark tidak rusak.
     */
    public function importBatches(Request $request)
    {
        return redirect()->route('jancode.packingLists');
    }

    /**
     * Daftar Packing List (group). Menampilkan revisi aktif tiap group.
     *
     * Mendukung filter opsional: style, ocf, destination, warehouse, dan q
     * (pencarian bebas pada label / batch / buyer).
     */
    public function packingLists(Request $request)
    {
        $currentSession = ImportSession::current();
        $sessionId      = $currentSession?->id;

        if ($request->ajax()) {
            $active = $this->applyPackingListFilters(
                    ImportBatch::active()->where('session_id', $sessionId ?? 0)->withCount('jancodes'),
                    $request
                )
                ->orderByDesc('id')
                ->get();

            $groupKeys = $active->pluck('group_key')->filter()->unique()->values();
            $packingListIds = $active->pluck('packing_list_id')->filter()->unique()->values();

            // Hitung jumlah revisi per group dalam session yang sama
            $revisionCounts = ImportBatch::whereIn('group_key', $groupKeys)
                ->where('session_id', $sessionId ?? 0)
                ->selectRaw('group_key, COUNT(*) as total')
                ->groupBy('group_key')
                ->pluck('total', 'group_key');

            // Total jancode seluruh revisi per packing list (untuk konfirmasi hapus).
            $jancodeTotals = $packingListIds->isEmpty()
                ? collect()
                : Jancode::whereIn('packing_list_id', $packingListIds)
                    ->selectRaw('packing_list_id, COUNT(*) as total')
                    ->groupBy('packing_list_id')
                    ->pluck('total', 'packing_list_id');

            $data = $active->map(function ($b) use ($revisionCounts, $jancodeTotals) {
                $failedCount = $b->failedRows()->count();
                return [
                    'id'              => $b->id,
                    'packing_list_id' => $b->packing_list_id,
                    'jancode_count'   => (int) ($b->jancodes_count ?? 0),
                    'jancode_total'   => (int) ($jancodeTotals[$b->packing_list_id] ?? ($b->jancodes_count ?? 0)),
                    'batch_number'    => $b->batch_number,
                    'doc_type'        => $b->doc_type ?: 'proforma',
                    'group_label'     => $b->group_label,
                    'style_number'    => $b->style_number,
                    'ocf_number'      => $b->ocf_number,
                    'destination'     => $b->destination,
                    'warehouse'       => $b->warehouse,
                    'supplier_name'   => $b->supplier_name,
                    'revision_no'     => $b->revision_no ?: 1,
                    'revision_count'  => $revisionCounts[$b->group_key] ?? 1,
                    'total_carton'    => $b->total_carton,
                    'valid_rows'      => $b->valid_rows,
                    'skipped_rows'    => $failedCount,
                    'import_date'     => $b->import_date ? $b->import_date->format('d/m/Y') : ($b->created_at ? $b->created_at->format('d/m/Y') : '-'),
                    'original_filename'=> $b->original_filename,
                ];
            });

            return response()->json(['data' => $data]);
        }

        return view('jancode.packing-lists', compact('currentSession'));
    }

    /**
     * Daftar jancode pada satu packing list (session aktif).
     *
     * Default menampilkan revisi aktif. Jika packing list punya beberapa
     * revisi, UI bisa mengirim ?batch_id=... untuk melihat revisi lain.
     */
    public function packingListJancodes(Request $request, PackingList $packingList)
    {
        $sessionId = ImportSession::currentId();

        if (!$sessionId || (int) $packingList->session_id !== (int) $sessionId) {
            return response()->json([
                'success' => false,
                'message' => 'Packing list tidak ditemukan pada session aktif.',
            ], 404);
        }

        $batches = ImportBatch::where('packing_list_id', $packingList->id)
            ->where('session_id', $sessionId)
            ->orderByDesc('revision_no')
            ->orderByDesc('id')
            ->get();

        $selected = null;
        if ($request->filled('batch_id')) {
            $selected = $batches->firstWhere('id', (int) $request->input('batch_id'));
        }
        if (!$selected) {
            $selected = $batches->firstWhere('is_active', true) ?: $batches->first();
        }

        $counts = $batches->isEmpty()
            ? collect()
            : Jancode::whereIn('import_batch_id', $batches->pluck('id'))
                ->selectRaw('import_batch_id, COUNT(*) as total')
                ->groupBy('import_batch_id')
                ->pluck('total', 'import_batch_id');

        $rows = collect();
        if ($selected) {
            $rows = Jancode::where('import_batch_id', $selected->id)
                ->orderBy('carton_no_from')
                ->orderBy('id')
                ->get()
                ->map(function ($j) {
                    return [
                        'id'                      => $j->id,
                        'source_row'              => $j->source_row,
                        'country'                 => $j->country,
                        'buyer'                   => $j->buyer,
                        'color'                   => $j->color,
                        'size'                    => $j->size,
                        'carton_no_from'          => $j->carton_no_from,
                        'carton_no_to'            => $j->carton_no_to,
                        'jancode'                 => $j->jancode,
                        'qty_per_carton'          => (int) $j->qty_per_carton,
                        'packing_list_qty_carton' => (int) $j->packing_list_qty_carton,
                        'receive_qty_carton'      => (int) $j->receive_qty_carton,
                        'balance'                 => (int) $j->balance,
                        'total_qty_pcs'           => (int) $j->total_qty_pcs,
                    ];
                });
        }

        return response()->json([
            'success'    => true,
            'packing_list' => [
                'id'          => $packingList->id,
                'label'       => $packingList->group_label ?: $packingList->group_key,
                'style'       => $packingList->style_number,
                'ocf'         => $packingList->ocf_number,
                'destination' => $packingList->destination,
                'warehouse'   => $packingList->warehouse,
                'session'     => $packingList->session?->code,
            ],
            'revisions' => $batches->map(function ($b) use ($counts) {
                return [
                    'id'            => $b->id,
                    'batch_number'  => $b->batch_number,
                    'doc_type'      => $b->doc_type ?: 'proforma',
                    'revision_no'   => $b->revision_no ?: 1,
                    'is_active'     => (bool) $b->is_active,
                    'valid_rows'    => (int) $b->valid_rows,
                    'total_carton'  => (int) $b->total_carton,
                    'jancode_count' => (int) ($counts[$b->id] ?? 0),
                    'import_date'   => $b->import_date
                        ? $b->import_date->format('d/m/Y')
                        : ($b->created_at ? $b->created_at->format('d/m/Y') : '-'),
                ];
            })->values(),
            'selected_batch_id' => $selected?->id,
            'summary' => [
                'rows'         => $rows->count(),
                'total_carton' => (int) $rows->sum('packing_list_qty_carton'),
                'received'     => (int) $rows->sum('receive_qty_carton'),
                'balance'      => (int) $rows->sum('balance'),
                'total_pcs'    => (int) $rows->sum('total_qty_pcs'),
            ],
            'data' => $rows->values(),
        ]);
    }

    /**
     * Hapus satu packing list beserta seluruh revisinya pada session aktif.
     *
     * Yang ikut dibersihkan:
     * - semua ImportBatch (revisi) + baris failed-nya
     * - semua jancode + scan log + scan batch
     * - penempatan pallet (detail dihapus, total pallet dihitung ulang,
     *   pallet kosong ikut dihapus)
     * - riwayat karton keluar yang memakai jancode tersebut
     */
    public function destroyPackingList(Request $request, PackingList $packingList)
    {
        $sessionId = ImportSession::currentId();

        if (!$sessionId || (int) $packingList->session_id !== (int) $sessionId) {
            return response()->json([
                'success' => false,
                'message' => 'Packing list tidak ditemukan pada session aktif.',
            ], 404);
        }

        $label = $packingList->group_label ?: $packingList->group_key;

        $summary = DB::transaction(function () use ($packingList, $sessionId) {
            $batchIds = ImportBatch::where('packing_list_id', $packingList->id)
                ->where('session_id', $sessionId)
                ->pluck('id')
                ->all();

            $jancodeIds = Jancode::where('packing_list_id', $packingList->id)
                ->pluck('id')
                ->all();

            // Jaga-jaga untuk baris lama yang import_batch_id-nya cocok tetapi
            // packing_list_id-nya belum terisi.
            if (!empty($batchIds)) {
                $extraIds = Jancode::whereIn('import_batch_id', $batchIds)->pluck('id')->all();
                $jancodeIds = array_values(array_unique(array_merge($jancodeIds, $extraIds)));
            }

            $summary = [
                'batches'        => count($batchIds),
                'jancodes'       => count($jancodeIds),
                'logs'           => 0,
                'scan_batches'   => 0,
                'pallet_details' => 0,
                'carton_outs'    => 0,
            ];

            // ── 1. Karton keluar (detail + header yang menjadi kosong) ──
            $plOutIds = CartonOut::where('packing_list_id', $packingList->id)->pluck('id')->all();

            $outIds = $plOutIds;
            if (!empty($jancodeIds)) {
                $outIds = array_values(array_unique(array_merge(
                    $outIds,
                    CartonOutDetail::whereIn('jancode_id', $jancodeIds)->pluck('carton_out_id')->all()
                )));
            }

            foreach ($outIds as $outId) {
                $out = CartonOut::find($outId);
                if (!$out) {
                    continue;
                }

                if (in_array($outId, $plOutIds, true)) {
                    CartonOutDetail::where('carton_out_id', $outId)->delete();
                } elseif (!empty($jancodeIds)) {
                    CartonOutDetail::where('carton_out_id', $outId)
                        ->whereIn('jancode_id', $jancodeIds)
                        ->delete();
                }

                $remaining = (int) CartonOutDetail::where('carton_out_id', $outId)->sum('qty_carton');
                if ($remaining <= 0) {
                    $out->delete();
                    $summary['carton_outs']++;
                } else {
                    $out->update(['total_carton' => $remaining]);
                }
            }

            // ── 2. Penempatan pallet ────────────────────────────────────
            $assignmentIds = PalletAssignmentDetail::where(function ($q) use ($jancodeIds, $packingList) {
                if (!empty($jancodeIds)) {
                    $q->whereIn('jancode_id', $jancodeIds);
                }
                $q->orWhere('packing_list_id', $packingList->id);
            })->pluck('assignment_id')->unique()->all();

            $palletDetailQuery = PalletAssignmentDetail::where(function ($q) use ($jancodeIds, $packingList) {
                if (!empty($jancodeIds)) {
                    $q->whereIn('jancode_id', $jancodeIds);
                }
                $q->orWhere('packing_list_id', $packingList->id);
            });

            $summary['pallet_details'] = (clone $palletDetailQuery)->count();
            $palletDetailQuery->delete();

            foreach ($assignmentIds as $assignmentId) {
                $assignment = PalletAssignment::lockForUpdate()->find($assignmentId);
                if (!$assignment) {
                    continue;
                }

                $total = (int) PalletAssignmentDetail::where('assignment_id', $assignmentId)->sum('qty_carton');
                if ($total <= 0) {
                    $assignment->delete();
                } else {
                    $assignment->update(['total_carton' => $total]);
                }
            }

            // ── 3. Scan log ─────────────────────────────────────────────
            $logQuery = JancodeLogs::where(function ($q) use ($jancodeIds, $packingList) {
                if (!empty($jancodeIds)) {
                    $q->whereIn('jancode_id', $jancodeIds);
                }
                $q->orWhere('packing_list_id', $packingList->id);
            });

            $summary['logs'] = (clone $logQuery)->count();
            $logQuery->delete();

            // ── 4. Scan batch ───────────────────────────────────────────
            $scanQuery = ScanBatch::where(function ($q) use ($jancodeIds, $packingList) {
                if (!empty($jancodeIds)) {
                    $q->whereIn('jancode_id', $jancodeIds);
                }
                $q->orWhere('packing_list_id', $packingList->id);
            });

            $summary['scan_batches'] = (clone $scanQuery)->count();
            $scanQuery->delete();

            // ── 5. Jancode ──────────────────────────────────────────────
            if (!empty($jancodeIds)) {
                Jancode::whereIn('id', $jancodeIds)->delete();
            }
            if (!empty($batchIds)) {
                Jancode::whereIn('import_batch_id', $batchIds)->delete();
            }

            // ── 6. Baris gagal + revisi ─────────────────────────────────
            if (!empty($batchIds)) {
                ImportFailedRow::whereIn('import_batch_id', $batchIds)->delete();
                ImportBatch::whereIn('id', $batchIds)->delete();
            }

            // ── 7. Packing list ─────────────────────────────────────────
            $packingList->delete();

            return $summary;
        });

        // Lock scan di session user tidak boleh menunjuk batch yang sudah hilang.
        session()->forget('scan_import_batch_id');

        return response()->json([
            'success' => true,
            'message' => "Packing list {$label} dihapus ({$summary['batches']} revisi, {$summary['jancodes']} jancode, {$summary['logs']} scan log).",
            'summary' => $summary,
        ]);
    }

    /**
     * Filter parent (style / ocf / destination / warehouse) untuk packing list.
     */
    private function applyPackingListParents($query, Request $request)
    {
        return $query
            ->when($request->filled('style'), fn($q) => $q->where('style_number', $request->input('style')))
            ->when($request->filled('ocf'), fn($q) => $q->where('ocf_number', $request->input('ocf')))
            ->when($request->filled('destination'), fn($q) => $q->where('destination', $request->input('destination')))
            ->when($request->filled('warehouse'), fn($q) => $q->where('warehouse', $request->input('warehouse')));
    }

    /**
     * Terapkan filter packing list lengkap (parent + pencarian bebas q).
     */
    private function applyPackingListFilters($query, Request $request)
    {
        $query = $this->applyPackingListParents($query, $request);

        if ($request->filled('q')) {
            $term = '%' . $request->input('q') . '%';
            $query->where(function ($w) use ($term) {
                $w->where('group_label', 'like', $term)
                  ->orWhere('batch_number', 'like', $term)
                  ->orWhere('supplier_name', 'like', $term)
                  ->orWhere('style_number', 'like', $term)
                  ->orWhere('ocf_number', 'like', $term);
            });
        }

        return $query;
    }

    /**
     * Opsi filter bertingkat (select2) untuk Manage Packing List.
     *
     * Contoh: field=ocf&style=BB2ZDA6A -> daftar OCF yang ada pada style tsb.
     * Parameter parent yang tidak diisi otomatis diabaikan; parameter q dipakai
     * untuk mencari nilai pada dropdown yang sedang dibuka saja.
     */
    public function packingListFilters(Request $request)
    {
        $column = match ($request->input('field')) {
            'style'       => 'style_number',
            'ocf'         => 'ocf_number',
            'destination' => 'destination',
            'warehouse'   => 'warehouse',
            default       => null,
        };

        if (!$column) {
            return response()->json([]);
        }

        $sessionId = ImportSession::currentId();

        $query = $this->applyPackingListParents(
                ImportBatch::active()->where('session_id', $sessionId ?? 0),
                $request
            )
            ->whereNotNull($column)
            ->where($column, '!=', '');

        if ($request->filled('q')) {
            $query->where($column, 'like', '%' . $request->input('q') . '%');
        }

        $options = $query->distinct()->orderBy($column)->pluck($column);

        return response()->json(
            $options->map(fn($value) => ['id' => $value, 'text' => $value])->values()
        );
    }

    /**
     * Detail satu batch (revisi) + riwayat revisi lain pada group yang sama.
     * Termasuk tabel terpisah untuk baris broken.
     */
    public function importBatchDetail(Request $request, $id)
    {
        $batch = ImportBatch::with(['importedBy'])->findOrFail($id);

        if ($request->ajax()) {
            $type = $request->input('type', 'items');

            if ($type === 'failed') {
                $failed = $batch->failedRows()->get()->map(fn($r) => [
                    'source_row'    => $r->source_row,
                    'reason_code'   => $r->reason_code,
                    'reason_label'  => $r->reason_label ?: (ImportFailedRow::$reasonLabels[$r->reason_code] ?? $r->reason_code),
                    'jan_code'      => $r->jan_code,
                    'color'         => $r->color,
                    'size'          => $r->size,
                    'keterangan'    => $r->keterangan,
                    'total_carton'  => $r->total_carton,
                    'carton_merged' => $r->carton_merged,
                    'notes'         => $r->notes,
                ]);
                return response()->json(['data' => $failed]);
            }

            $items = $batch->jancodes()->get()->map(fn($j) => [
                'id'                      => $j->id,
                'country'                 => $j->country,
                'buyer'                   => $j->buyer,
                'ocf'                     => $j->ocf,
                'style'                   => $j->style,
                'carton_no_from'          => $j->carton_no_from,
                'carton_no_to'            => $j->carton_no_to,
                'color'                   => $j->color,
                'size'                    => $j->size,
                'jancode'                 => $j->jancode,
                'packing_list_qty_carton' => $j->packing_list_qty_carton,
                'receive_qty_carton'      => $j->receive_qty_carton,
                'balance'                 => $j->balance,
                'qty_per_carton'          => $j->qty_per_carton,
                'total_qty_pcs'           => $j->total_qty_pcs,
            ]);
            return response()->json(['data' => $items]);
        }

        // Riwayat revisi lain pada group yang sama (termasuk non-aktif)
        $revisions = ImportBatch::forGroup($batch->group_key, $batch->session_id)
            ->orderByDesc('revision_no')
            ->get(['id', 'batch_number', 'doc_type', 'revision_no', 'is_active', 'valid_rows', 'invalid_rows', 'total_carton', 'created_at', 'original_filename']);

        return view('jancode.import-batch-detail', compact('batch', 'revisions'));
    }

    public function exportTemplate()
    {
        return Excel::download(new JancodeTemplateExport, 'jancode_template.xlsx');
    }
}
