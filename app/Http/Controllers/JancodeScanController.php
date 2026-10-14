<?php

namespace App\Http\Controllers;

use App\Models\Jancode;
use App\Models\JancodeLogs;
use App\Models\ScanBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JancodeScanController extends Controller
{
    // Halaman utama scanner
    public function index()
    {
        return view('jancode.scanner');
    }

    public function search(Request $request)
    {
        $q = $request->input('q');
        $query = Jancode::where('void', 'false');

        if ($q) {
            $query->where(function($w) use ($q) {
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
            $totalAll = JancodeLogs::where('jancode_id', $target->id)->count();
            $scanned = JancodeLogs::where('jancode_id', $target->id)->where('is_submitted', 'true')->count();
            $unsubmitted = JancodeLogs::where('jancode_id', $target->id)->where('is_submitted', 'false')->count();
            $balance = $target->qty - $totalAll;
            
            // Format yang ditampilkan di Select2
            $text = "{$target->jancode} | Size: {$target->size} | Qty: {$target->qty} (Sisa: {$balance})";
            
            $results[] = [
                'id'          => $target->id,
                'text'        => $text,
                'jancode'     => $target->jancode,
                'size'        => $target->size,
                'qty'         => $target->qty,
                'scanned'     => $scanned,
                'unsubmitted' => $unsubmitted,
                'total_all'   => $totalAll,
                'balance'     => $balance,
            ];
        }

        return response()->json($results);
    }

    // POST scan — simpan log
    public function scan(Request $request)
    {
        $barcode = $request->input('jancode');
        $jancodeId = $request->input('jancode_id');

        if (!$barcode || !$jancodeId) {
            return response()->json([
                'success' => false,
                'message' => 'Jancode atau Target tidak boleh kosong'
            ], 400);
        }

        $lockedId = session('locked_jancode_id');

        // Check if there's a lock and we're trying to scan a different target
        if ($lockedId && $jancodeId != $lockedId) {
            $lockedJancode = Jancode::find($lockedId);
            $lockedScannedCount = JancodeLogs::where('jancode_id', $lockedId)->where('is_submitted', 'true')->count();
            $lockedUnsubmittedCount = JancodeLogs::where('jancode_id', $lockedId)->where('is_submitted', 'false')->count();
            $lockedTotalAll = JancodeLogs::where('jancode_id', $lockedId)->count();

            // Auto-unlock if the locked target is actually already completed
            if ($lockedJancode && $lockedTotalAll >= $lockedJancode->qty) {
                session()->forget('locked_jancode_id');
                $lockedId = null;
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'locked',
                    'message' => 'Target berbeda dengan scan sebelumnya. Selesaikan atau reset scan terlebih dahulu.',
                    'scanned' => $lockedScannedCount,
                    'unsubmitted' => $lockedUnsubmittedCount,
                    'total_all' => $lockedTotalAll,
                    'qty' => $lockedJancode ? $lockedJancode->qty : 0,
                    'destination' => $lockedJancode ? $lockedJancode->destination : null,
                    'buyer' => $lockedJancode ? $lockedJancode->buyer : null,
                    'style' => $lockedJancode ? $lockedJancode->style : null,
                    'cpo' => $lockedJancode ? $lockedJancode->cpo : null,
                    'locked_jancode_id' => $lockedId
                ], 403);
            }
        }

        $count = $request->input('count', 1);

        try {
            DB::beginTransaction();

            $master = Jancode::where('id', $jancodeId)->where('jancode', $barcode)->where('void', 'false')->lockForUpdate()->first();

            if (!$master) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'error'   => 'not_found',
                    'message' => 'Barcode tidak cocok dengan Target atau sudah di-void',
                ], 404);
            }

            // Cek jika sudah over qty
            $totalAllCount = JancodeLogs::where('jancode_id', $jancodeId)->count();

            if ($totalAllCount + $count > $master->qty) {
                DB::rollBack();
                session()->forget('locked_jancode_id');

                $scannedCount = JancodeLogs::where('jancode_id', $jancodeId)->where('is_submitted', 'true')->count();
                $unsubmittedCount = JancodeLogs::where('jancode_id', $jancodeId)->where('is_submitted', 'false')->count();

                return response()->json([
                    'success' => false,
                    'error'   => 'over',
                    'message' => 'Qty sudah terpenuhi, tidak bisa scan lebih dari sisa (' . ($master->qty - $totalAllCount) . ' tersisa)',
                    'scanned' => $scannedCount,
                    'unsubmitted' => $unsubmittedCount,
                    'total_all' => $totalAllCount,
                    'qty'     => $master->qty,
                ], 422);
            }

            if (!$lockedId) {
                session(['locked_jancode_id' => $jancodeId]);
            }

            $logsToInsert = [];
            $now = now();
            for ($i = 0; $i < $count; $i++) {
                $logsToInsert[] = [
                    'jancode' => $barcode,
                    'jancode_id' => $jancodeId,
                    'user_id' => auth()->id(),
                    'is_submitted' => 'false',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            JancodeLogs::insert($logsToInsert);

            DB::commit();

            $scannedCount = JancodeLogs::where('jancode_id', $jancodeId)->where('is_submitted', 'true')->count();
            $unsubmittedCount = JancodeLogs::where('jancode_id', $jancodeId)->where('is_submitted', 'false')->count();
            $totalAllCount += $count;

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem saat menyimpan data',
                'error' => $e->getMessage()
            ], 500);
        }

        return response()->json([
            'success'     => true,
            'scanned'     => $scannedCount,
            'unsubmitted' => $unsubmittedCount,
            'total_all'   => $totalAllCount,
            'qty'         => $master->qty,
            'is_complete' => $totalAllCount >= $master->qty,
            'destination' => $master->destination,
            'buyer'       => $master->buyer,
            'style'       => $master->style,
            'cpo'         => $master->cpo,
            'size'        => $master->size,
            'jancode'     => $master->jancode,
        ]);
    }

    // GET count — refresh counters (used after void)
    public function count(Request $request)
    {
        $jancodeId = $request->input('jancode_id');
        if (!$jancodeId) {
            return response()->json(['scanned' => 0, 'unsubmitted' => 0, 'total_all' => 0, 'qty' => 0]);
        }

        $master = Jancode::where('id', $jancodeId)
                         ->where('void', 'false')
                         ->first();

        if (!$master) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $scannedCount = JancodeLogs::where('jancode_id', $jancodeId)->where('is_submitted', 'true')->count();
        $unsubmittedCount = JancodeLogs::where('jancode_id', $jancodeId)->where('is_submitted', 'false')->count();
        $totalAllCount = JancodeLogs::where('jancode_id', $jancodeId)->count();

        // tambahan
        $logs = JancodeLogs::where('jancode_id', $jancodeId)
            ->latest('created_at')
            ->limit(50)
            ->get();

        return response()->json([
            'scanned'     => $scannedCount,
            'unsubmitted' => $unsubmittedCount,
            'total_all'   => $totalAllCount,
            'qty'         => $master->qty,
            'is_complete' => $totalAllCount >= $master->qty,
            'destination' => $master->destination,
            'buyer'       => $master->buyer,
            'style'       => $master->style,
            'cpo'         => $master->cpo,
            'size'        => $master->size,
            // tambahan
            'color'       => $master->color,
            'description' => $master->description,
            'jancode'     => $master->jancode,
            'logs'        => $logs,
        ]);
    }

    public function submit(Request $request)
    {
        $jancodeId = $request->input('jancode_id');
        if (!$jancodeId) {
            return response()->json(['success' => false, 'message' => 'Target tidak boleh kosong'], 400);
        }

        try {
            DB::beginTransaction();

            $master = Jancode::where('id', $jancodeId)->where('void', 'false')->lockForUpdate()->first();
            if (!$master) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Target tidak ditemukan'], 404);
            }

            // 1. Hitung jumlah scan sementara yang akan disubmit pada batch ini
            $unsubmittedCount = JancodeLogs::where('jancode_id', $jancodeId)->where('is_submitted', 'false')->count();
            if ($unsubmittedCount <= 0) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Tidak ada scan sementara untuk disubmit'], 400);
            }

            // 2. Generate Batch Number unik: BSCAN-YYYYMMDD-XXXX
            $today = date('Ymd');
            $prefix = "BSCAN-{$today}-";
            $lastBatch = ScanBatch::where('batch_no', 'like', "{$prefix}%")
                ->orderByDesc('id')
                ->first();
            $nextSeq = 1;
            if ($lastBatch) {
                $lastSeq = (int)substr($lastBatch->batch_no, strlen($prefix));
                $nextSeq = $lastSeq + 1;
            }
            $batchNo = $prefix . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);

            // 3. Hitung akumulasi received & sisa balance
            $totalMasterCarton = (int)($master->packing_list_qty_carton ?: $master->qty);
            $alreadyScannedCount = JancodeLogs::where('jancode_id', $jancodeId)->where('is_submitted', 'true')->count();
            $newReceiveQty = $alreadyScannedCount + $unsubmittedCount;
            $newBalance = max(0, $totalMasterCarton - $newReceiveQty);

            // 4. Simpan record ScanBatch (History Batch Scan)
            $scanBatch = ScanBatch::create([
                'batch_no'    => $batchNo,
                'jancode_id'  => $jancodeId,
                'user_id'     => auth()->id(),
                'scanned_qty' => $unsubmittedCount,
                'receive_qty' => $newReceiveQty,
                'balance'     => $newBalance,
                'total_qty'   => $totalMasterCarton,
                'notes'       => "Batch scan {$unsubmittedCount} karton disubmit oleh " . (auth()->user()?->name ?? 'Operator'),
            ]);

            // 5. Update JancodeLogs dengan scan_batch_id dan status is_submitted = 'true'
            JancodeLogs::where('jancode_id', $jancodeId)
                ->where('is_submitted', 'false')
                ->update([
                    'is_submitted'  => 'true',
                    'user_id'       => auth()->id(),
                    'scan_batch_id' => $scanBatch->id,
                ]);

            // 6. Update master Jancode
            $master->update([
                'receive_qty_carton' => $newReceiveQty,
                'balance'            => $newBalance,
            ]);

            $totalAllCount = JancodeLogs::where('jancode_id', $jancodeId)->count();
            if ($totalAllCount >= $totalMasterCarton) {
                session()->forget('locked_jancode_id');
            }

            DB::commit();

            return response()->json([
                'success'           => true,
                'message'           => "Batch scan {$batchNo} berhasil disubmit ({$unsubmittedCount} karton).",
                'batch_no'          => $batchNo,
                'batch_id'          => $scanBatch->id,
                'batch_scanned_qty' => $unsubmittedCount,
                'scanned'           => $newReceiveQty,
                'unsubmitted'       => 0,
                'total_all'         => $newReceiveQty,
                'qty'               => $totalMasterCarton,
                'balance'           => $newBalance,
                'description'       => $master->description ?? null,
                'size'              => $master->size ?? null,
                'color'             => $master->color ?? null,
                'is_complete'       => ($newReceiveQty >= $totalMasterCarton),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem saat submit batch scan: ' . $e->getMessage(),
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Riwayat Batch Scan (List JSON untuk DataTables / View)
     */
    public function batchHistory(Request $request)
    {
        if ($request->ajax()) {
            $jancodeId = $request->input('jancode_id');

            $query = ScanBatch::with(['jancode', 'user'])->latest();
            if ($jancodeId) {
                $query->where('jancode_id', $jancodeId);
            }

            $batches = $query->get()->map(function ($b) {
                $j = $b->jancode;
                return [
                    'id'          => $b->id,
                    'batch_no'    => $b->batch_no,
                    'created_at'  => $b->created_at ? $b->created_at->format('d/m/Y H:i:s') : '-',
                    'jancode'     => $j ? $j->jancode : '-',
                    'color'       => $j ? $j->color : '-',
                    'size'        => $j ? $j->size : '-',
                    'style'       => $j ? $j->style : '-',
                    'country'     => $j ? ($j->country ?: $j->destination) : '-',
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
        $jancodeId = $request->input('jancode_id');
        
        try {
            DB::beginTransaction();
            
            $master = Jancode::where('id', $jancodeId)->lockForUpdate()->first();
            
            $last = JancodeLogs::where('jancode_id', $jancodeId)
                               ->where('is_submitted', 'false')
                               ->orderBy('created_at', 'desc')
                               ->first();

            if ($last) {
                $last->delete();
                
                $scannedCount = JancodeLogs::where('jancode_id', $jancodeId)->where('is_submitted', 'true')->count();
                $unsubmittedCount = JancodeLogs::where('jancode_id', $jancodeId)->where('is_submitted', 'false')->count();
                $totalAllCount = JancodeLogs::where('jancode_id', $jancodeId)->count();
                
                // If we void and fall below qty, we lock the session again
                if ($master && $totalAllCount < $master->qty) {
                    session(['locked_jancode_id' => $jancodeId]);
                }

                DB::commit();
                
                return response()->json([
                    'success' => true,
                    'scanned' => $scannedCount,
                    'unsubmitted' => $unsubmittedCount,
                    'total_all' => $totalAllCount,
                    'qty' => $master ? $master->qty : 0,
                    'is_complete' => $master ? ($totalAllCount >= $master->qty) : false
                ]);
            }
            
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => 'Tidak ada log scan terakhir yang bisa dibatalkan',
            ], 404);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    public function rollback(Request $request)
    {
        $jancodeId = $request->input('jancode_id');
        if (!$jancodeId) {
             return response()->json(['success' => false, 'message' => 'Target tidak boleh kosong'], 400);
        }
        
        try {
            DB::beginTransaction();
            
            $master = Jancode::where('id', $jancodeId)->lockForUpdate()->first();
            
            $deletedRows = JancodeLogs::where('jancode_id', $jancodeId)
                               ->where('is_submitted', 'false')
                               ->delete();

            if ($deletedRows > 0) {
                $scannedCount = JancodeLogs::where('jancode_id', $jancodeId)->where('is_submitted', 'true')->count();
                $unsubmittedCount = 0;
                $totalAllCount = JancodeLogs::where('jancode_id', $jancodeId)->count();
                
                if ($master && $totalAllCount < $master->qty) {
                    session(['locked_jancode_id' => $jancodeId]);
                }

                DB::commit();
                return response()->json([
                    'success' => true,
                    'scanned' => $scannedCount,
                    'unsubmitted' => $unsubmittedCount,
                    'total_all' => $totalAllCount,
                    'qty' => $master ? $master->qty : 0,
                    'is_complete' => $master ? ($totalAllCount >= $master->qty) : false
                ]);
            }
            
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => 'Tidak ada scan sementara yang bisa di-rollback',
            ], 404);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem saat rollback scan',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    public function resetLock(Request $request)
    {
        session()->forget('locked_jancode_id');
        return response()->json(['success' => true]);
    }
}