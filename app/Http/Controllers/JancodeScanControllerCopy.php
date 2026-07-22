<?php

namespace App\Http\Controllers;

use App\Models\Jancode;
use App\Models\JancodeLogs;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;

class JancodeScanControllerCopy extends Controller
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
            $scanned = JancodeLogs::where('jancode_id', $target->id)->count();
            $balance = $target->qty - $scanned;
            
            // Format yang ditampilkan di Select2
            $text = "{$target->jancode} | Size: {$target->size} | Qty: {$target->qty} (Sisa: {$balance})";
            
            $results[] = [
                'id' => $target->id,
                'text' => $text,
                'jancode' => $target->jancode,
                'size' => $target->size
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
            $lockedUnsubmittedCount = JancodeLogs::where('jancode_id', $lockedId)->where('user_id', auth()->id())->where('is_submitted', 'false')->count();
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
                $unsubmittedCount = JancodeLogs::where('jancode_id', $jancodeId)->where('user_id', auth()->id())->where('is_submitted', 'false')->count();

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
            $unsubmittedCount = JancodeLogs::where('jancode_id', $jancodeId)->where('user_id', auth()->id())->where('is_submitted', 'false')->count();
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
        $unsubmittedCount = JancodeLogs::where('jancode_id', $jancodeId)->where('user_id', auth()->id())->where('is_submitted', 'false')->count();
        $totalAllCount = JancodeLogs::where('jancode_id', $jancodeId)->count();

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

            JancodeLogs::where('jancode_id', $jancodeId)
                ->where('user_id', auth()->id())
                ->where('is_submitted', 'false')
                ->update(['is_submitted' => 'true']);

            $scannedCount = JancodeLogs::where('jancode_id', $jancodeId)->where('is_submitted', 'true')->count();
            $unsubmittedCount = JancodeLogs::where('jancode_id', $jancodeId)->where('user_id', auth()->id())->where('is_submitted', 'false')->count();
            $totalAllCount = JancodeLogs::where('jancode_id', $jancodeId)->count();

            if ($totalAllCount >= $master->qty) {
                session()->forget('locked_jancode_id');
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'scanned' => $scannedCount,
                'unsubmitted' => $unsubmittedCount,
                'total_all' => $totalAllCount,
                'qty' => $master->qty,
                'description' => $master->description ?? null,
                'size' => $master->size ?? null,
                'color' => $master->color ?? null,
                'is_complete' => ($totalAllCount >= $master->qty)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem saat submit',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // DELETE — void scan terakhir
    public function voidLast(Request $request)
    {
        $jancodeId = $request->input('jancode_id');
        
        try {
            DB::beginTransaction();
            
            $master = Jancode::where('id', $jancodeId)->lockForUpdate()->first();
            
            $last = JancodeLogs::where('jancode_id', $jancodeId)
                               ->where('user_id', auth()->id())
                               ->where('is_submitted', 'false')
                               ->orderBy('created_at', 'desc')
                               ->first();

            if ($last) {
                $last->delete();
                
                $scannedCount = JancodeLogs::where('jancode_id', $jancodeId)->where('is_submitted', 'true')->count();
                $unsubmittedCount = JancodeLogs::where('jancode_id', $jancodeId)->where('user_id', auth()->id())->where('is_submitted', 'false')->count();
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
                               ->where('user_id', auth()->id())
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