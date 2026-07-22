<?php

namespace App\Http\Controllers;

use App\Exports\JancodeSheet\JancodeTemplateExport;
use App\Exports\JancodeSheet\ScanLogsExport;
use App\Models\Jancode;
use App\Models\JancodeLogs;
use App\Imports\JancodesImport;
use App\Imports\JancodesNewFormatImport;
use Illuminate\Http\Request;
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
                ->addColumn('scanned', function ($row) use ($scanCounts) {
                    return $scanCounts[$row->id] ?? 0;
                })
                ->addColumn('balance', function ($row) use ($scanCounts) {
                    $scanned = $scanCounts[$row->id] ?? 0;
                    $balance = $row->qty - $scanned;
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

        return response()->json([
            'destination'  => $jancode->destination,
            'buyer'        => $jancode->buyer,
            'style'        => $jancode->style,
            'cpo'          => $jancode->cpo,
            'qty'          => $jancode->qty,
            'total_scans'  => $total,
            'balance'      => $jancode->qty - $total,
            'logs'         => $logs,
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

    public function exportTemplate()
    {
        return Excel::download(new JancodeTemplateExport, 'jancode_template.xlsx');
    }
}
