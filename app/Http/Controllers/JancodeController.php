<?php

namespace App\Http\Controllers;

use App\Models\Jancode;
use App\Imports\JancodesImport;
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
            return Datatables::of($data)
                ->addIndexColumn()
                ->addColumn('action', function($row){
                    $btn = '<a href="javascript:void(0)" data-id="'.$row->id.'" class="edit btn btn-primary btn-sm btn-circle editJancode"><i class="fas fa-edit"></i></a> ';
                    $btn = $btn.' <a href="javascript:void(0)" data-id="'.$row->id.'" class="btn btn-danger btn-sm btn-circle deleteJancode"><i class="fas fa-trash"></i></a>';
                    return $btn;
                })
                ->rawColumns(['action'])
                ->make(true);
        }
        
        return view('jancode.index');
    }

    public function store(Request $request)
    {
        $request->validate([
            'jancode' => 'required|string',
            'size' => 'required|string',
            'qty' => 'required|integer',
            'country' => 'required|string',
            'color' => 'required|string',
            'description' => 'required|string',
            'void' => 'sometimes|boolean'
        ]);

        $jancode = Jancode::updateOrCreate(
            ['id' => $request->id],
            [
                'jancode' => $request->jancode,
                'size' => $request->size,
                'qty' => $request->qty,
                'country' => $request->country,
                'color' => $request->color,
                'description' => $request->description,
                'void' => $request->void ?? 0,
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
}
