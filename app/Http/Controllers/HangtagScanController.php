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
            return response()->json(['scanned' => 0, 'qty' => 0]);
        }

        $hangtag = Hangtag::where('barcode', $barcode)->first();
        if (!$hangtag) {
            return response()->json(['scanned' => 0, 'qty' => 0]);
        }

        $scannedCount = HangtagLogs::where('barcode', $barcode)->count();

        return response()->json([
            'scanned'     => $scannedCount,
            'qty'         => $hangtag->qty,
            'buyer'       => $hangtag->buyer,
            'size'        => $hangtag->size,
            'color'       => $hangtag->color,
            'is_complete' => ($scannedCount >= $hangtag->qty)
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
            $lockedScannedCount = HangtagLogs::where('barcode', $lockedBarcode)->count();

            // Auto-unlock if the locked barcode is actually already completed
            if ($lockedHangtag && $lockedScannedCount >= $lockedHangtag->qty) {
                session()->forget('locked_hangtag_barcode');
                $lockedBarcode = null;
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'locked',
                    'message' => 'Barcode berbeda dengan scan sebelumnya. Selesaikan atau reset scan terlebih dahulu.',
                    'scanned' => $lockedScannedCount,
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
            $scannedCount = HangtagLogs::where('barcode', $barcode)->count();
            if ($scannedCount >= $hangtag->qty) {
                DB::rollBack();
                // Ensure lock is cleared if it's already full
                session()->forget('locked_hangtag_barcode');
                return response()->json([
                    'success' => false,
                    'error' => 'over',
                    'message' => 'Qty sudah terpenuhi, tidak bisa scan lagi',
                    'scanned' => $scannedCount,
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
                'user_id' => auth()->id()
            ]);

            DB::commit();
            $scannedCount++;

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem saat menyimpan data',
                'error' => $e->getMessage()
            ], 500);
        }

        // Unlock if complete
        if ($scannedCount >= $hangtag->qty) {
            session()->forget('locked_hangtag_barcode');
        }

        return response()->json([
            'success'     => true,
            'scanned'     => $scannedCount,
            'qty'         => $hangtag->qty,
            'buyer'       => $hangtag->buyer,
            'size'        => $hangtag->size,
            'color'       => $hangtag->color,
            'is_complete' => ($scannedCount >= $hangtag->qty)
        ]);
    }

    public function voidLast(Request $request)
    {
        $barcode = $request->input('barcode');
        
        try {
            DB::beginTransaction();
            
            // Kunci baris Hangtag agar selaras dengan proses scan
            $hangtag = Hangtag::where('barcode', $barcode)->lockForUpdate()->first();
            
            $lastLog = HangtagLogs::where('barcode', $barcode)
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
    
    public function resetLock(Request $request)
    {
        session()->forget('locked_hangtag_barcode');
        return response()->json(['success' => true]);
    }
}
