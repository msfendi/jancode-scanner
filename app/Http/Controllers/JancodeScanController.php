<?php

namespace App\Http\Controllers;

use App\Models\Jancode;
use App\Models\JancodeLogs;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;

class JancodeScanController extends Controller
{
    // Halaman utama scanner
    public function index()
    {
        return view('jancode.scanner');
    }

    // POST scan — simpan log
    public function scan(Request $request)
    {
        $barcode = $request->input('jancode');
        if (!$barcode) {
            return response()->json([
                'success' => false,
                'message' => 'Jancode tidak boleh kosong'
            ], 400);
        }

        $lockedBarcode = session('locked_jancode_barcode');

        // Check if there's a lock and we're trying to scan a different barcode
        if ($lockedBarcode && $barcode !== $lockedBarcode) {
            $lockedJancode = Jancode::where('jancode', $lockedBarcode)->first();
            $lockedScannedCount = JancodeLogs::where('jancode', $lockedBarcode)->where('is_submitted', 'true')->count();
            $lockedUnsubmittedCount = JancodeLogs::where('jancode', $lockedBarcode)->where('user_id', auth()->id())->where('is_submitted', 'false')->count();
            $lockedTotalAll = JancodeLogs::where('jancode', $lockedBarcode)->count();

            // Auto-unlock if the locked barcode is actually already completed
            if ($lockedJancode && $lockedTotalAll >= $lockedJancode->qty) {
                session()->forget('locked_jancode_barcode');
                $lockedBarcode = null;
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'locked',
                    'message' => 'Jancode berbeda dengan scan sebelumnya. Selesaikan atau reset scan terlebih dahulu.',
                    'scanned' => $lockedScannedCount,
                    'unsubmitted' => $lockedUnsubmittedCount,
                    'total_all' => $lockedTotalAll,
                    'qty' => $lockedJancode ? $lockedJancode->qty : 0,
                    'description' => $lockedJancode ? $lockedJancode->description : null,
                    'size' => $lockedJancode ? $lockedJancode->size : null,
                    'color' => $lockedJancode ? $lockedJancode->color : null,
                    'locked_barcode' => $lockedBarcode
                ], 403);
            }
        }

        $count = $request->input('count', 1);

        try {
            DB::beginTransaction();

            $master = Jancode::where('jancode', $barcode)->where('void', 'false')->lockForUpdate()->first();

            if (!$master) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'error'   => 'not_found',
                    'message' => 'Jancode tidak ditemukan atau sudah di-void',
                ], 404);
            }

            // Cek jika sudah over qty
            $totalAllCount = JancodeLogs::where('jancode', $barcode)->count();

            if ($totalAllCount + $count > $master->qty) {
                DB::rollBack();
                session()->forget('locked_jancode_barcode');

                $scannedCount = JancodeLogs::where('jancode', $barcode)->where('is_submitted', 'true')->count();
                $unsubmittedCount = JancodeLogs::where('jancode', $barcode)->where('user_id', auth()->id())->where('is_submitted', 'false')->count();

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

            if (!$lockedBarcode) {
                session(['locked_jancode_barcode' => $barcode]);
            }

            $logsToInsert = [];
            $now = now();
            for ($i = 0; $i < $count; $i++) {
                $logsToInsert[] = [
                    'jancode' => $barcode,
                    'user_id' => auth()->id(),
                    'is_submitted' => 'false',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            JancodeLogs::insert($logsToInsert);

            DB::commit();

            $scannedCount = JancodeLogs::where('jancode', $barcode)->where('is_submitted', 'true')->count();
            $unsubmittedCount = JancodeLogs::where('jancode', $barcode)->where('user_id', auth()->id())->where('is_submitted', 'false')->count();
            $totalAllCount += $count;

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem saat menyimpan data',
                'error' => $e->getMessage()
            ], 500);
        }

        if ($totalAllCount >= $master->qty) {
            session()->forget('locked_jancode_barcode');
        }

        return response()->json([
            'success'     => true,
            'scanned'     => $scannedCount,
            'unsubmitted' => $unsubmittedCount,
            'total_all'   => $totalAllCount,
            'qty'         => $master->qty,
            'is_complete' => $totalAllCount >= $master->qty,
            'description' => $master->description,
            'size'        => $master->size,
            'color'       => $master->color,
        ]);
    }

    // GET count — refresh counters (used after void)
    public function count(Request $request)
    {
        $barcode = $request->jancode;
        if (!$barcode) {
            return response()->json(['scanned' => 0, 'unsubmitted' => 0, 'total_all' => 0, 'qty' => 0]);
        }

        $master = Jancode::where('jancode', $barcode)
                         ->where('void', 'false')
                         ->first();

        if (!$master) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $scannedCount = JancodeLogs::where('jancode', $barcode)->where('is_submitted', 'true')->count();
        $unsubmittedCount = JancodeLogs::where('jancode', $barcode)->where('user_id', auth()->id())->where('is_submitted', 'false')->count();
        $totalAllCount = JancodeLogs::where('jancode', $barcode)->count();

        return response()->json([
            'scanned'     => $scannedCount,
            'unsubmitted' => $unsubmittedCount,
            'total_all'   => $totalAllCount,
            'qty'         => $master->qty,
            'is_complete' => $totalAllCount >= $master->qty,
            'description' => $master->description,
            'size'        => $master->size,
            'color'       => $master->color,
        ]);
    }

    public function submit(Request $request)
    {
        $barcode = $request->input('jancode');
        if (!$barcode) {
            return response()->json(['success' => false, 'message' => 'Jancode tidak boleh kosong'], 400);
        }

        try {
            DB::beginTransaction();

            $master = Jancode::where('jancode', $barcode)->where('void', 'false')->lockForUpdate()->first();
            if (!$master) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Jancode tidak ditemukan'], 404);
            }

            JancodeLogs::where('jancode', $barcode)
                ->where('user_id', auth()->id())
                ->where('is_submitted', 'false')
                ->update(['is_submitted' => 'true']);

            $scannedCount = JancodeLogs::where('jancode', $barcode)->where('is_submitted', 'true')->count();
            $unsubmittedCount = JancodeLogs::where('jancode', $barcode)->where('user_id', auth()->id())->where('is_submitted', 'false')->count();
            $totalAllCount = JancodeLogs::where('jancode', $barcode)->count();

            if ($totalAllCount >= $master->qty) {
                session()->forget('locked_jancode_barcode');
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'scanned' => $scannedCount,
                'unsubmitted' => $unsubmittedCount,
                'total_all' => $totalAllCount,
                'qty' => $master->qty,
                'description' => $master->description,
                'size' => $master->size,
                'color' => $master->color,
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
        $barcode = $request->input('jancode');
        
        try {
            DB::beginTransaction();
            
            $master = Jancode::where('jancode', $barcode)->lockForUpdate()->first();
            
            $last = JancodeLogs::where('jancode', $barcode)
                               ->where('user_id', auth()->id())
                               ->where('is_submitted', 'false')
                               ->orderBy('created_at', 'desc')
                               ->first();

            if ($last) {
                $last->delete();
                DB::commit();
                return response()->json([
                    'success' => true,
                    'message' => 'Scan terakhir berhasil di-void',
                ]);
            }
            
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => 'Tidak ada scan sementara yang bisa di-void',
            ], 404);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem saat membatalkan scan',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    public function rollback(Request $request)
    {
        $barcode = $request->input('jancode');
        if (!$barcode) {
             return response()->json(['success' => false, 'message' => 'Jancode tidak boleh kosong'], 400);
        }
        
        try {
            DB::beginTransaction();
            
            $master = Jancode::where('jancode', $barcode)->lockForUpdate()->first();
            
            $deletedRows = JancodeLogs::where('jancode', $barcode)
                               ->where('user_id', auth()->id())
                               ->where('is_submitted', 'false')
                               ->delete();

            if ($deletedRows > 0) {
                DB::commit();
                return response()->json([
                    'success' => true,
                    'message' => "Berhasil menghapus $deletedRows data scan sementara.",
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
        session()->forget('locked_jancode_barcode');
        return response()->json(['success' => true]);
    }
}