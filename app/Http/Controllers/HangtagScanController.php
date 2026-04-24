<?php

namespace App\Http\Controllers;

use App\Models\Hangtag;
use App\Models\HangtagLogs;
use Illuminate\Http\Request;

class HangtagScanController extends Controller
{
    public function index($buyer)
    {
        return view('hangtag.scanner', compact('buyer'));
    }

    public function count(Request $request, $buyer)
    {
        $barcode = $request->barcode;
        if (!$barcode) {
            return response()->json(['scanned' => 0, 'qty' => 0]);
        }

        $hangtag = Hangtag::where('buyer', $buyer)->where('barcode', $barcode)->first();
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

    public function scan(Request $request, $buyer)
    {
        $barcode = $request->input('barcode');
        $hangtag = Hangtag::where('buyer', $buyer)->where('barcode', $barcode)->first();

        if (!$hangtag) {
            return response()->json([
                'success' => false,
                'message' => 'Hangtag tidak ditemukan untuk buyer ' . $buyer
            ], 404);
        }

        // Insert new log atomicaly
        HangtagLogs::create([
            'barcode' => $barcode
        ]);

        $scannedCount = HangtagLogs::where('barcode', $barcode)->count();

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

    public function voidLast(Request $request, $buyer)
    {
        $barcode = $request->input('barcode');
        
        $lastLog = HangtagLogs::where('barcode', $barcode)
            ->orderBy('created_at', 'desc')
            ->first();

        if ($lastLog) {
            $lastLog->delete();
            return response()->json([
                'success' => true,
                'message' => 'Scan terakhir berhasil dihapus'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Tidak ada history scan'
        ], 404);
    }
}
