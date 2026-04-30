<?php

namespace App\Http\Controllers;

use App\Exports\HangtagSheet\HangtagScanLogsExport;
use App\Imports\HangtagsImport;
use App\Models\Hangtag;
use App\Models\HangtagLogs;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Yajra\DataTables\Facades\DataTables;

class HangtagController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = Hangtag::latest()->get();

            // Pre-load scan counts
            $scanCounts = HangtagLogs::selectRaw('barcode, COUNT(*) as total')
                ->groupBy('barcode')
                ->pluck('total', 'barcode');

            return Datatables::of($data)
                ->addIndexColumn()
                ->addColumn('scanned', function ($row) use ($scanCounts) {
                    return $scanCounts[$row->barcode] ?? 0;
                })
                ->addColumn('balance', function ($row) use ($scanCounts) {
                    $scanned = $scanCounts[$row->barcode] ?? 0;
                    $balance = $row->qty - $scanned;
                    if ($balance < 0) {
                        return '<span class="badge badge-danger">' . $balance . '</span>';
                    } elseif ($balance === 0) {
                        return '<span class="badge badge-success">0 ✓</span>';
                    }
                    return '<span class="badge badge-warning text-dark">' . $balance . '</span>';
                })
                ->addColumn('action', function ($row) {
                    $btn  = '<a href="javascript:void(0)" data-id="' . $row->id . '" data-barcode="' . $row->barcode . '" class="btn btn-info btn-sm btn-circle detailScan" title="Detail Scan"><i class="fas fa-search"></i></a> ';
                    $btn .= '<a href="' . route('hangtag.exportScanLogs', ['id' => $row->id]) . '" class="btn btn-success btn-sm btn-circle" title="Export Log"><i class="fas fa-file-excel"></i></a> ';
                    $btn .= '<a href="javascript:void(0)" data-id="' . $row->id . '" class="edit btn btn-primary btn-sm btn-circle editHangtag" title="Edit"><i class="fas fa-edit"></i></a> ';
                    $btn .= '<a href="javascript:void(0)" data-id="' . $row->id . '" class="btn btn-danger btn-sm btn-circle deleteHangtag" title="Delete"><i class="fas fa-trash"></i></a>';
                    return $btn;
                })
                ->rawColumns(['action', 'balance'])
                ->make(true);
        }

        return view('hangtag.index');
    }

    public function exportScanLogs($id)
    {
        $hangtag = Hangtag::findOrFail($id);

        $filename = 'hangtag_scan_log_' . $hangtag->barcode . '_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new HangtagScanLogsExport($hangtag), $filename);
    }

    public function store(Request $request)
    {
        $request->validate([
            'barcode' => 'required|string',
            'qty'     => 'required|integer',
            'country' => 'required|string',
            'color'   => 'required|string',
            'size'    => 'required|string',
            'buyer'   => 'sometimes|string|nullable',
            'void'    => 'sometimes|boolean'
        ]);

        Hangtag::updateOrCreate(
            ['id' => $request->id],
            [
                'barcode' => $request->barcode,
                'qty'     => $request->qty,
                'country' => $request->country,
                'color'   => $request->color,
                'size'    => $request->size,
                'buyer'   => $request->buyer,
                'void'    => $request->void ?? 0,
            ]
        );

        return response()->json(['success' => 'Hangtag saved successfully.']);
    }

    public function edit($id)
    {
        $hangtag = Hangtag::findOrFail($id);
        return response()->json($hangtag);
    }

    public function destroy($id)
    {
        Hangtag::findOrFail($id)->delete();
        return response()->json(['success' => 'Hangtag deleted successfully.']);
    }

    public function scanLogs($id)
    {
        $hangtag = Hangtag::findOrFail($id);

        $logs = HangtagLogs::where('barcode', $hangtag->barcode)
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

        return response()->json([
            'barcode'      => $hangtag->barcode,
            'buyer'        => $hangtag->buyer,
            'size'         => $hangtag->size,
            'color'        => $hangtag->color,
            'qty'          => $hangtag->qty,
            'total_scans'  => $total,
            'balance'      => $hangtag->qty - $total,
            'logs'         => $logs,
        ]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv'
        ]);

        Excel::import(new HangtagsImport, $request->file('file'));

        return response()->json(['message' => 'Hangtags imported successfully!']);
    }
}
