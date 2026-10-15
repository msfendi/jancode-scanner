<?php

namespace App\Http\Controllers;

use App\Exports\JancodeSheet\JancodeTemplateExport;
use App\Exports\JancodeSheet\ScanLogsExport;
use App\Models\Jancode;
use App\Models\JancodeLogs;
use App\Models\ImportBatch;
use App\Models\ImportFailedRow;
use App\Models\ScanBatch;
use App\Imports\JancodesImport;
use App\Imports\JancodesNewFormatImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Yajra\DataTables\Facades\DataTables;
use RealRashid\SweetAlert\Facades\Alert;

class JancodeController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = Jancode::latest()->get();

            // Pre-load scan counts in one query
            $scanCounts = JancodeLogs::selectRaw('jancode_id, COUNT(*) as total')
                ->whereNotNull('jancode_id')
                ->groupBy('jancode_id')
                ->pluck('total', 'jancode_id');

            return Datatables::of($data)
                ->addIndexColumn()
                ->addColumn('country', function ($row) {
                    return $row->country ?: ($row->destination ?: '-');
                })
                ->addColumn('ocf', function ($row) {
                    return $row->ocf ?: ($row->cpo ?: '-');
                })
                ->addColumn('packing_qty', function ($row) {
                    return $row->packing_list_qty_carton ?: ($row->qty ?: 0);
                })
                ->addColumn('qty_per_carton', function ($row) {
                    return $row->qty_per_carton ?: 0;
                })
                ->addColumn('total_qty_pcs', function ($row) {
                    $pcs = $row->total_qty_pcs ?: (($row->packing_list_qty_carton ?: $row->qty ?: 0) * ($row->qty_per_carton ?: 0));
                    return number_format($pcs);
                })
                ->addColumn('received_qty', function ($row) use ($scanCounts) {
                    return $row->receive_qty_carton ?: ($scanCounts[$row->id] ?? 0);
                })
                ->addColumn('balance', function ($row) use ($scanCounts) {
                    $packing = $row->packing_list_qty_carton ?: ($row->qty ?: 0);
                    $received = $row->receive_qty_carton ?: ($scanCounts[$row->id] ?? 0);
                    $balance = $packing - $received;
                    if ($balance < 0) {
                        return '<span class="badge badge-danger">' . $balance . '</span>';
                    } elseif ($balance === 0) {
                        return '<span class="badge badge-success">0 ✓</span>';
                    }
                    return '<span class="badge badge-warning text-dark">' . $balance . '</span>';
                })
                ->addColumn('action', function ($row) {
                    $btn  = '<a href="javascript:void(0)" data-id="' . $row->id . '" data-jancode="' . $row->jancode . '" class="btn btn-info btn-sm btn-circle detailScan" title="Detail Scan"><i class="fas fa-search"></i></a> ';
                    $btn .= '<a href="' . route('jancode.exportScanLogs', $row->id) . '" class="btn btn-success btn-sm btn-circle" title="Export Log"><i class="fas fa-file-excel"></i></a> ';
                    $btn .= '<a href="javascript:void(0)" data-id="' . $row->id . '" class="edit btn btn-primary btn-sm btn-circle editJancode" title="Edit"><i class="fas fa-edit"></i></a> ';
                    $btn .= '<a href="javascript:void(0)" data-id="' . $row->id . '" class="btn btn-danger btn-sm btn-circle deleteJancode" title="Delete"><i class="fas fa-trash"></i></a>';
                    return $btn;
                })
                ->rawColumns(['action', 'balance'])
                ->make(true);
        }

        return view('jancode.index');
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

        $batches = ScanBatch::where('jancode_id', $jancode->id)
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

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv'
        ]);

        Excel::import(new JancodesImport, $request->file('file'));

        return response()->json(['message' => 'Jancodes imported successfully!']);
    }

    public function importNewFormat(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv'
        ]);

        Excel::import(new JancodesNewFormatImport, $request->file('file'));

        return response()->json(['message' => 'Jancodes (new format) imported successfully!']);
    }

    /**
     * Preview AI-Assisted Import
     */
    public function previewAiImport(Request $request, \App\Services\JancodeImportService $service)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:15360'
        ]);

        try {
            $uploadedFile = $request->file('file');
            $originalName = $uploadedFile->getClientOriginalName();
            $preview = $service->parseFile($uploadedFile->getRealPath());

            // Tandai duplikat terhadap data eksisting
            $existingKeys = Jancode::select('ocf', 'style', 'color', 'size', 'jancode')
                ->get()
                ->map(fn($item) => strtolower(trim("{$item->ocf}|{$item->style}|{$item->color}|{$item->size}|{$item->jancode}")))
                ->flip()
                ->toArray();

            foreach ($preview['items'] as &$item) {
                $key = strtolower(trim("{$item['ocf']}|{$item['style']}|{$item['color']}|{$item['size']}|{$item['jan_code']}"));
                $item['is_duplicate'] = isset($existingKeys[$key]);
            }
            unset($item);

            return response()->json([
                'success'          => true,
                'data'             => $preview,
                'original_filename'=> $originalName,
            ]);
        } catch (\Throwable $e) {
            Log::error('AI Import Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses file Excel: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * Confirm and save AI-Assisted Import
     */
    public function confirmAiImport(Request $request)
    {
        $request->validate([
            'items'                          => 'required|array|min:1',
            'items.*.country'                => 'required|string',
            'items.*.buyer'                  => 'required|string',
            'items.*.ocf'                    => 'required|string',
            'items.*.style'                  => 'required|string',
            'items.*.color'                  => 'required|string',
            'items.*.size'                   => 'required|string',
            'items.*.jan_code'               => 'required|string',
            'items.*.packing_list_qty_carton'=> 'required|integer|min:1',
            // Batch metadata (opsional, boleh tidak dikirim)
            'original_filename'              => 'nullable|string',
            'supplier_name'                  => 'nullable|string',
            'batch_name'                     => 'nullable|string',
            'skipped'                        => 'nullable|array',
        ]);

        $items      = $request->input('items');
        $skipped    = $request->input('skipped', []);
        $header     = collect($items)->first(); // ambil ocf/supplier dari item pertama

        $importedCount = 0;
        $updatedCount  = 0;
        $batch         = null;

        DB::transaction(function () use ($items, $skipped, $header, $request, &$importedCount, &$updatedCount, &$batch) {
            // 1. Buat ImportBatch
            $batch = ImportBatch::create([
                'batch_number'      => ImportBatch::generateBatchNumber(),
                'batch_name'        => $request->input('batch_name') ?: ('Import ' . now()->format('d/m/Y H:i')),
                'original_filename' => $request->input('original_filename'),
                'supplier_name'     => $request->input('supplier_name') ?: ($header['buyer'] ?? null),
                'ocf_number'        => $header['ocf'] ?? null,
                'cpo'               => $header['ocf'] ?? null,
                'import_date'       => now()->toDateString(),
                'status'            => 'processing',
                'total_rows'        => count($items) + count($skipped),
                'valid_rows'        => count($items),
                'invalid_rows'      => count($skipped),
                'total_items'       => count($items),
                'processed_items'   => 0,
                'imported_by'       => auth()->id(),
            ]);

            // 2. Import valid items
            foreach ($items as $item) {
                $existing = Jancode::where('ocf', $item['ocf'])
                    ->where('style', $item['style'])
                    ->where('color', $item['color'])
                    ->where('size', $item['size'])
                    ->where('jancode', $item['jan_code'])
                    ->first();

                $packingQty   = (int)$item['packing_list_qty_carton'];
                $qtyPerCarton = (int)($item['qty_per_carton'] ?? 0);
                $totalPcs     = $packingQty * $qtyPerCarton;

                $cartonFrom   = isset($item['carton_no_from']) && $item['carton_no_from'] !== '' ? (int)$item['carton_no_from'] : null;
                $cartonTo     = isset($item['carton_no_to']) && $item['carton_no_to'] !== '' ? (int)$item['carton_no_to'] : null;
                $sourceRow    = isset($item['source_row']) ? (int)$item['source_row'] : null;
                $nwPerCtn     = isset($item['nw_per_carton']) && $item['nw_per_carton'] !== '' ? (float)$item['nw_per_carton'] : null;
                $ttlNw        = isset($item['ttl_nw']) && $item['ttl_nw'] !== '' ? (float)$item['ttl_nw'] : null;
                $gwPerCtn     = isset($item['gw_per_carton']) && $item['gw_per_carton'] !== '' ? (float)$item['gw_per_carton'] : null;
                $ttlGw        = isset($item['ttl_gw']) && $item['ttl_gw'] !== '' ? (float)$item['ttl_gw'] : null;
                $measCm       = $item['measurement_cm'] ?? null;
                $cbm          = isset($item['cbm']) && $item['cbm'] !== '' ? (float)$item['cbm'] : null;

                if ($existing) {
                    $receiveQty = (int)($existing->receive_qty_carton ?? 0);
                    $existing->update([
                        'import_batch_id'         => $batch->id,
                        'source_row'              => $sourceRow,
                        'country'                 => $item['country'],
                        'buyer'                   => $item['buyer'],
                        'carton_no_from'          => $cartonFrom,
                        'carton_no_to'            => $cartonTo,
                        'qty_per_carton'          => $qtyPerCarton,
                        'packing_list_qty_carton' => $packingQty,
                        'qty'                     => $packingQty,
                        'destination'             => $item['country'],
                        'cpo'                     => $item['ocf'],
                        'balance'                 => max(0, $packingQty - $receiveQty),
                        'total_qty_pcs'           => $totalPcs,
                        'nw_per_carton'           => $nwPerCtn,
                        'ttl_nw'                  => $ttlNw,
                        'gw_per_carton'           => $gwPerCtn,
                        'ttl_gw'                  => $ttlGw,
                        'measurement_cm'          => $measCm,
                        'cbm'                     => $cbm,
                    ]);
                    $updatedCount++;
                } else {
                    Jancode::create([
                        'import_batch_id'         => $batch->id,
                        'source_row'              => $sourceRow,
                        'country'                 => $item['country'],
                        'buyer'                   => $item['buyer'],
                        'ocf'                     => $item['ocf'],
                        'style'                   => $item['style'],
                        'carton_no_from'          => $cartonFrom,
                        'carton_no_to'            => $cartonTo,
                        'color'                   => $item['color'],
                        'size'                    => $item['size'],
                        'qty_per_carton'          => $qtyPerCarton,
                        'jancode'                 => $item['jan_code'],
                        'packing_list_qty_carton' => $packingQty,
                        'receive_qty_carton'      => 0,
                        'balance'                 => $packingQty,
                        'total_qty_pcs'           => $totalPcs,
                        'nw_per_carton'           => $nwPerCtn,
                        'ttl_nw'                  => $ttlNw,
                        'gw_per_carton'           => $gwPerCtn,
                        'ttl_gw'                  => $ttlGw,
                        'measurement_cm'          => $measCm,
                        'cbm'                     => $cbm,
                        'qty'                     => $packingQty,
                        'destination'             => $item['country'],
                        'cpo'                     => $item['ocf'],
                        'void'                    => 'false',
                    ]);
                    $importedCount++;
                }
            }

            // 3. Simpan failed/skipped rows
            $reasonLabels = ImportFailedRow::$reasonLabels;
            foreach ($skipped as $skip) {
                ImportFailedRow::create([
                    'import_batch_id' => $batch->id,
                    'source_row'      => (string)($skip['source_row'] ?? ''),
                    'reason_code'     => $skip['reason'] ?? 'OTHER',
                    'reason_label'    => $skip['reason_label'] ?? ($reasonLabels[$skip['reason'] ?? 'OTHER'] ?? $skip['reason'] ?? 'Unknown'),
                    'jan_code'        => $skip['jan_code'] ?? null,
                    'color'           => $skip['color'] ?? null,
                    'size'            => $skip['size'] ?? null,
                    'keterangan'      => $skip['keterangan'] ?? null,
                    'total_carton'    => isset($skip['total_carton']) ? (int)$skip['total_carton'] : null,
                    'carton_merged'   => (bool)($skip['carton_merged'] ?? false),
                    'raw_data'        => isset($skip['raw_data']) ? $skip['raw_data'] : $skip,
                    'notes'           => $skip['notes'] ?? null,
                ]);
            }

            // 4. Update status batch
            $status = 'completed';
            if (count($skipped) > 0 && ($importedCount + $updatedCount) > 0) {
                $status = 'partial';
            } elseif (($importedCount + $updatedCount) === 0) {
                $status = 'failed';
            }

            $batch->update([
                'status'          => $status,
                'processed_items' => $importedCount + $updatedCount,
                'import_notes'    => "Baru: {$importedCount}, Update: {$updatedCount}, Gagal: " . count($skipped),
            ]);
        });

        return response()->json([
            'success'      => true,
            'message'      => "Import berhasil: {$importedCount} data baru ditambahkan, {$updatedCount} data diperbarui.",
            'batch_number' => $batch->batch_number,
            'batch_id'     => $batch->id,
            'failed_count' => count($skipped),
        ]);
    }

    /**
     * Riwayat Import Batch
     */
    public function importBatches(Request $request)
    {
        if ($request->ajax()) {
            $batches = ImportBatch::with('importedBy')
                ->latest()
                ->get()
                ->map(function ($b) {
                    return [
                        'id'                => $b->id,
                        'batch_number'      => $b->batch_number,
                        'batch_name'        => $b->batch_name,
                        'original_filename' => $b->original_filename,
                        'supplier_name'     => $b->supplier_name,
                        'ocf_number'        => $b->ocf_number,
                        'import_date'       => $b->import_date ? $b->import_date->format('d/m/Y') : '-',
                        'status'            => $b->status,
                        'total_rows'        => $b->total_rows,
                        'valid_rows'        => $b->valid_rows,
                        'invalid_rows'      => $b->invalid_rows,
                        'processed_items'   => $b->processed_items,
                        'imported_by'       => $b->importedBy ? $b->importedBy->name : '-',
                        'import_notes'      => $b->import_notes,
                        'created_at'        => $b->created_at->format('d/m/Y H:i'),
                    ];
                });

            return response()->json(['data' => $batches]);
        }

        return view('jancode.import-batches');
    }

    /**
     * Detail satu batch: item valid + failed rows
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
                'color'                   => $j->color,
                'size'                    => $j->size,
                'jancode'                 => $j->jancode,
                'packing_list_qty_carton' => $j->packing_list_qty_carton,
                'qty_per_carton'          => $j->qty_per_carton,
                'total_qty_pcs'           => $j->total_qty_pcs,
            ]);
            return response()->json(['data' => $items]);
        }

        return view('jancode.import-batch-detail', compact('batch'));
    }

    public function exportTemplate()
    {
        return Excel::download(new JancodeTemplateExport, 'jancode_template.xlsx');
    }
}
