<?php

namespace App\Http\Controllers;

use App\Exports\JancodeSheet\JancodeTemplateExport;
use App\Exports\JancodeSheet\ScanLogsExport;
use App\Models\Jancode;
use App\Models\JancodeLogs;
use App\Models\ImportBatch;
use App\Models\ImportFailedRow;
use App\Models\ImportSession;
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

            // Satu kombinasi style|OCF|destination|warehouse = satu session.
            // Jika kombinasi file ini sudah dimiliki session lain, arahkan ke
            // session tersebut (buka kembali bila sudah ditutup).
            $fileGroupKeys = collect($preview['sheets'])
                ->map(fn ($s) => ImportBatch::buildGroupKey($s['header']))
                ->unique()
                ->values();

            if ($fileGroupKeys->count() === 1) {
                $owner = ImportSession::findByGroup($fileGroupKeys->first(), $importSession->id);

                if ($owner) {
                    return response()->json([
                        'success'     => false,
                        'error'       => 'session_exists',
                        'message'     => 'Kombinasi ' . $owner->group_label . ' sudah ada pada session '
                            . $owner->code . ' (' . strtoupper($owner->status) . '). '
                            . 'Gunakan / buka kembali session tersebut agar data tidak tercampur.',
                        'session'     => [
                            'id'          => $owner->id,
                            'code'        => $owner->code,
                            'status'      => $owner->status,
                            'is_open'     => $owner->isOpen(),
                            'group_label' => $owner->group_label,
                        ],
                        'reopen_url'  => route('session.reopen', $owner),
                        'status_url'  => route('session.index'),
                    ], 409);
                }
            }

            // Semua sheet harus memiliki info yang sama dengan session aktif.
            $mismatch = [];
            foreach ($preview['sheets'] as $sheet) {
                $gk = ImportBatch::buildGroupKey($sheet['header']);
                if (!$importSession->acceptsGroup($gk)) {
                    $mismatch[] = ImportBatch::buildGroupLabel($sheet['header']);
                }
            }

            if (!empty($mismatch)) {
                return response()->json([
                    'success' => false,
                    'error'   => 'group_mismatch',
                    'message' => 'File berisi packing list dengan info berbeda dari session ' . $importSession->code . ': '
                        . implode(', ', array_unique($mismatch))
                        . '. Buat session baru untuk packing list tersebut.',
                ], 422);
            }

            // Tandai duplikat terhadap data eksisting pada packing list (group) yang sama
            foreach ($preview['sheets'] as &$sheet) {
                $groupKey = ImportBatch::buildGroupKey($sheet['header']);
                $active   = ImportBatch::activeForGroup($groupKey, $importSession->id);

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

                $sheet['group_key']       = $groupKey;
                $sheet['group_label']     = ImportBatch::buildGroupLabel($sheet['header']);
                $sheet['next_revision']   = ImportBatch::nextRevisionForGroup($groupKey, $importSession->id);
                $sheet['active_batch_no'] = $active->batch_number ?? null;
            }
            unset($sheet);

            return response()->json([
                'success'          => true,
                'data'             => $preview,
                'original_filename'=> $originalName,
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

        foreach ($sheets as $sheet) {
            $gk = ImportBatch::buildGroupKey($sheet['header'] ?? []);
            if (!$importSession->acceptsGroup($gk)) {
                return response()->json([
                    'success' => false,
                    'error'   => 'group_mismatch',
                    'message' => 'Packing list ini memiliki style/OCF/destination/warehouse berbeda dari session '
                        . $importSession->code . '. Buat session baru untuk packing list tersebut.',
                ], 422);
            }
        }

        $meta = [
            'session_id'        => $importSession->id,
            'original_filename' => $request->input('original_filename'),
            'batch_name'        => $request->input('batch_name'),
            'supplier_name'     => $request->input('supplier_name'),
            'imported_by'       => auth()->id(),
        ];

        $created       = [];
        $importedCount = 0;
        $skippedCount  = 0;

        try {
            DB::transaction(function () use ($sheets, $meta, $importService, &$created, &$importedCount, &$skippedCount) {
                foreach ($sheets as $sheet) {
                    $result = $importService->importSheet($sheet, $meta);
                    if (!$result) {
                        continue;
                    }

                    /** @var ImportBatch $batch */
                    $batch = $result['batch'];

                    $created[] = [
                        'batch_id'     => $batch->id,
                        'batch_number' => $batch->batch_number,
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

        return response()->json([
            'success'      => true,
            'message'      => "Import berhasil: {$importedCount} baris valid pada " . count($created) . " packing list. {$skippedCount} baris broken disimpan terpisah.",
            'batches'      => $created,
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
                    ImportBatch::active()->where('session_id', $sessionId ?? 0),
                    $request
                )
                ->orderByDesc('id')
                ->get();

            $groupKeys = $active->pluck('group_key')->filter()->unique()->values();

            // Hitung jumlah revisi per group dalam session yang sama
            $revisionCounts = ImportBatch::whereIn('group_key', $groupKeys)
                ->where('session_id', $sessionId ?? 0)
                ->selectRaw('group_key, COUNT(*) as total')
                ->groupBy('group_key')
                ->pluck('total', 'group_key');

            $data = $active->map(function ($b) use ($revisionCounts) {
                $failedCount = $b->failedRows()->count();
                return [
                    'id'              => $b->id,
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
