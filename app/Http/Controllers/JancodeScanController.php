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
            $lockedScannedCount = JancodeLogs::where('jancode', $lockedBarcode)->count();

            // Auto-unlock if the locked barcode is actually already completed
            if ($lockedJancode && $lockedScannedCount >= $lockedJancode->qty) {
                session()->forget('locked_jancode_barcode');
                $lockedBarcode = null;
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'locked',
                    'message' => 'Jancode berbeda dengan scan sebelumnya. Selesaikan atau reset scan terlebih dahulu.',
                    'scanned' => $lockedScannedCount,
                    'qty' => $lockedJancode ? $lockedJancode->qty : 0,
                    'description' => $lockedJancode ? $lockedJancode->description : null,
                    'size' => $lockedJancode ? $lockedJancode->size : null,
                    'color' => $lockedJancode ? $lockedJancode->color : null,
                    'locked_barcode' => $lockedBarcode
                ], 403);
            }
        }

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
            $scanned = JancodeLogs::where('jancode', $barcode)->count();

            if ($scanned >= $master->qty) {
                DB::rollBack();
                session()->forget('locked_jancode_barcode');
                return response()->json([
                    'success' => false,
                    'error'   => 'over',
                    'message' => 'Qty sudah terpenuhi, tidak bisa scan lagi',
                    'scanned' => $scanned,
                    'qty'     => $master->qty,
                ], 422);
            }

            if (!$lockedBarcode) {
                session(['locked_jancode_barcode' => $barcode]);
            }

            JancodeLogs::create([
                'jancode' => $barcode,
                'user_id' => auth()->id()
            ]);

            DB::commit();
            $newCount = $scanned + 1;

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem saat menyimpan data',
                'error' => $e->getMessage()
            ], 500);
        }

        if ($newCount >= $master->qty) {
            session()->forget('locked_jancode_barcode');
        }

        return response()->json([
            'success'     => true,
            'scanned'     => $newCount,
            'qty'         => $master->qty,
            'is_complete' => $newCount >= $master->qty,
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
            return response()->json(['scanned' => 0, 'qty' => 0]);
        }

        $master = Jancode::where('jancode', $barcode)
                         ->where('void', 'false')
                         ->first();

        if (!$master) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $scanned = JancodeLogs::where('jancode', $barcode)->count();

        return response()->json([
            'scanned'     => $scanned,
            'qty'         => $master->qty,
            'is_complete' => $scanned >= $master->qty,
            'description' => $master->description,
            'size'        => $master->size,
            'color'       => $master->color,
        ]);
    }

    // DELETE — void scan terakhir
    public function voidLast(Request $request)
    {
        $barcode = $request->input('jancode');
        
        try {
            DB::beginTransaction();
            
            $master = Jancode::where('jancode', $barcode)->lockForUpdate()->first();
            
            $last = JancodeLogs::where('jancode', $barcode)
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
                'error' => 'Tidak ada scan yang bisa di-void',
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
        session()->forget('locked_jancode_barcode');
        return response()->json(['success' => true]);
    }
}