<?php

namespace App\Http\Controllers;

use App\Models\Jancode;
use App\Models\JancodeLogs;
use Illuminate\Http\Request;

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
        $request->validate([
            'jancode' => 'required|string',
        ]);

        $master = Jancode::where('jancode', $request->jancode)
                         ->where('void', 'false')
                         ->first();

        if (!$master) {
            return response()->json([
                'error'   => 'not_found',
                'message' => 'Jancode tidak ditemukan atau sudah di-void',
            ], 404);
        }

        // Cek jika sudah over qty
        $scanned = JancodeLogs::where('jancode', $request->jancode)->count();

        if ($scanned >= $master->qty) {
            return response()->json([
                'error'   => 'over',
                'message' => 'Qty sudah terpenuhi, tidak bisa scan lagi',
                'scanned' => $scanned,
                'qty'     => $master->qty,
            ], 422);
        }

        JancodeLogs::create([
            'jancode' => $request->jancode,
        ]);

        $newCount = $scanned + 1;

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
        $request->validate([
            'jancode' => 'required|string',
        ]);

        $master = Jancode::where('jancode', $request->jancode)
                         ->where('void', 'false')
                         ->first();

        if (!$master) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $scanned = JancodeLogs::where('jancode', $request->jancode)->count();

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
        $request->validate([
            'jancode' => 'required|string',
        ]);

        $last = JancodeLogs::where('jancode', $request->jancode)
                           ->latest()
                           ->first();

        if (!$last) {
            return response()->json([
                'error' => 'Tidak ada scan yang bisa di-void',
            ], 404);
        }

        $last->delete();

        return response()->json([
            'success' => true,
            'message' => 'Scan terakhir berhasil di-void',
        ]);
    }
}