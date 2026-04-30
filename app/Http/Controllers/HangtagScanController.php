<?php

namespace App\Http\Controllers;

use App\Models\Hangtag;
use App\Models\HangtagLogs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HangtagScanController extends Controller
{
    public function index()
    {
        return view('hangtag.scanner');
    }

    public function count(Request $request)
    {
        $barcode = $request->barcode;
        if (!$barcode) {
            return response()->json(['scanned' => 0, 'unsubmitted' => 0, 'total_all' => 0, 'qty' => 0]);
        }

        $hangtag = Hangtag::where('barcode', $barcode)->first();
        if (!$hangtag) {
            return response()->json(['scanned' => 0, 'unsubmitted' => 0, 'total_all' => 0, 'qty' => 0]);
        }

        $scannedCount = HangtagLogs::where('barcode', $barcode)->where('is_submitted', 'true')->count();
        $unsubmittedCount = HangtagLogs::where('barcode', $barcode)->where('user_id', auth()->id())->where('is_submitted', 'false')->count();
        $totalAllCount = HangtagLogs::where('barcode', $barcode)->count();

        return response()->json([
            'scanned'     => $scannedCount,
            'unsubmitted' => $unsubmittedCount,
            'total_all'   => $totalAllCount,
            'qty'         => $hangtag->qty,
            'buyer'       => $hangtag->buyer,
            'size'        => $hangtag->size,
            'color'       => $hangtag->color,
            'is_complete' => ($totalAllCount >= $hangtag->qty)
        ]);
    }

    public function scan(Request $request)
    {
        $barcode = $request->input('barcode');
        if (!$barcode) {
            return response()->json([
                'success' => false,
                'message' => 'Barcode tidak boleh kosong'
            ], 400);
        }
        
        $lockedBarcode = session('locked_hangtag_barcode');

        // Check if there's a lock and we're trying to scan a different barcode
        if ($lockedBarcode && $barcode !== $lockedBarcode) {
            $lockedHangtag = Hangtag::where('barcode', $lockedBarcode)->first();
            $lockedScannedCount = HangtagLogs::where('barcode', $lockedBarcode)->where('is_submitted', 'true')->count();
            $lockedUnsubmittedCount = HangtagLogs::where('barcode', $lockedBarcode)->where('user_id', auth()->id())->where('is_submitted', 'false')->count();
            $lockedTotalAll = HangtagLogs::where('barcode', $lockedBarcode)->count();

            // Auto-unlock if the locked barcode is actually already completed
            if ($lockedHangtag && $lockedTotalAll >= $lockedHangtag->qty) {
                session()->forget('locked_hangtag_barcode');
                $lockedBarcode = null;
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'locked',
                    'message' => 'Barcode berbeda dengan scan sebelumnya. Selesaikan atau reset scan terlebih dahulu.',
                    'scanned' => $lockedScannedCount,
                    'unsubmitted' => $lockedUnsubmittedCount,
                    'total_all' => $lockedTotalAll,
                    'qty' => $lockedHangtag ? $lockedHangtag->qty : 0,
                    'buyer' => $lockedHangtag ? $lockedHangtag->buyer : null,
                    'size' => $lockedHangtag ? $lockedHangtag->size : null,
                    'color' => $lockedHangtag ? $lockedHangtag->color : null,
                    'locked_barcode' => $lockedBarcode
                ], 403);
            }
        }

        try {
            DB::beginTransaction();

            // Kunci baris Hangtag ini agar tidak bisa dibaca/diubah proses lain yang bersamaan
            $hangtag = Hangtag::where('barcode', $barcode)->lockForUpdate()->first();

            if (!$hangtag) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'error' => 'not_found',
                    'message' => 'Hangtag tidak ditemukan'
                ], 404);
            }

            // Hitung aktual secara real-time
            $totalAllCount = HangtagLogs::where('barcode', $barcode)->count();
            if ($totalAllCount >= $hangtag->qty) {
                DB::rollBack();
                // Ensure lock is cleared if it's already full
                session()->forget('locked_hangtag_barcode');

                $scannedCount = HangtagLogs::where('barcode', $barcode)->where('is_submitted', 'true')->count();
                $unsubmittedCount = HangtagLogs::where('barcode', $barcode)->where('user_id', auth()->id())->where('is_submitted', 'false')->count();

                return response()->json([
                    'success' => false,
                    'error' => 'over',
                    'message' => 'Qty sudah terpenuhi, tidak bisa scan lagi',
                    'scanned' => $scannedCount,
                    'unsubmitted' => $unsubmittedCount,
                    'total_all' => $totalAllCount,
                    'qty' => $hangtag->qty,
                ], 422);
            }

            // Lock the barcode if it's not locked yet
            if (!$lockedBarcode) {
                session(['locked_hangtag_barcode' => $barcode]);
            }

            // Insert new log atomicaly
            HangtagLogs::create([
                'barcode' => $barcode,
                'user_id' => auth()->id(),
                'is_submitted' => 'false'
            ]);

            DB::commit();
            
            $scannedCount = HangtagLogs::where('barcode', $barcode)->where('is_submitted', 'true')->count();
            $unsubmittedCount = HangtagLogs::where('barcode', $barcode)->where('user_id', auth()->id())->where('is_submitted', 'false')->count();
            $totalAllCount = HangtagLogs::where('barcode', $barcode)->count();

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem saat menyimpan data',
                'error' => $e->getMessage()
            ], 500);
        }

        // Unlock if complete
        if ($totalAllCount >= $hangtag->qty) {
            session()->forget('locked_hangtag_barcode');
        }

        return response()->json([
            'success'     => true,
            'scanned'     => $scannedCount,
            'unsubmitted' => $unsubmittedCount,
            'total_all'   => $totalAllCount,
            'qty'         => $hangtag->qty,
            'buyer'       => $hangtag->buyer,
            'size'        => $hangtag->size,
            'color'       => $hangtag->color,
            'is_complete' => ($totalAllCount >= $hangtag->qty)
        ]);
    }
    
    public function submit(Request $request)
    {
        $barcode = $request->input('barcode');
        if (!$barcode) {
            return response()->json(['success' => false, 'message' => 'Barcode tidak boleh kosong'], 400);
        }

        try {
            DB::beginTransaction();

            $hangtag = Hangtag::where('barcode', $barcode)->lockForUpdate()->first();
            if (!$hangtag) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Hangtag tidak ditemukan'], 404);
            }

            // Update user's unsubmitted scans to submitted
            HangtagLogs::where('barcode', $barcode)
                ->where('user_id', auth()->id())
                ->where('is_submitted', 'false')
                ->update(['is_submitted' => 'true']);

            $scannedCount = HangtagLogs::where('barcode', $barcode)->where('is_submitted', 'true')->count();
            $unsubmittedCount = HangtagLogs::where('barcode', $barcode)->where('user_id', auth()->id())->where('is_submitted', 'false')->count();
            $totalAllCount = HangtagLogs::where('barcode', $barcode)->count();

            if ($totalAllCount >= $hangtag->qty) {
                session()->forget('locked_hangtag_barcode');
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'scanned' => $scannedCount,
                'unsubmitted' => $unsubmittedCount,
                'total_all' => $totalAllCount,
                'qty' => $hangtag->qty,
                'buyer' => $hangtag->buyer,
                'size' => $hangtag->size,
                'color' => $hangtag->color,
                'is_complete' => ($totalAllCount >= $hangtag->qty)
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

    public function voidLast(Request $request)
    {
        $barcode = $request->input('barcode');
        
        try {
            DB::beginTransaction();
            
            // Kunci baris Hangtag agar selaras dengan proses scan
            $hangtag = Hangtag::where('barcode', $barcode)->lockForUpdate()->first();
            
            $lastLog = HangtagLogs::where('barcode', $barcode)
                ->where('user_id', auth()->id())
                ->where('is_submitted', 'false')
                ->orderBy('created_at', 'desc')
                ->first();

            if ($lastLog) {
                $lastLog->delete();
                DB::commit();
                return response()->json([
                    'success' => true,
                    'message' => 'Scan terakhir berhasil dihapus'
                ]);
            }
            
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada history scan'
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
        $barcode = $request->input('barcode');
        if (!$barcode) {
             return response()->json(['success' => false, 'message' => 'Barcode tidak boleh kosong'], 400);
        }
        
        try {
            DB::beginTransaction();
            
            $master = Hangtag::where('barcode', $barcode)->lockForUpdate()->first();
            
            $deletedRows = HangtagLogs::where('barcode', $barcode)
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
        session()->forget('locked_hangtag_barcode');
        return response()->json(['success' => true]);
    }
}
