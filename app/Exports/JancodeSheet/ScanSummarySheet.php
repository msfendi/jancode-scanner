<?php

namespace App\Exports;

use App\Models\JancodeLogs;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class ScanSummarySheet implements FromView, WithTitle, ShouldAutoSize
{
    protected $jancode;

    public function __construct($jancode)
    {
        $this->jancode = $jancode;
    }

    public function view(): View
    {
        $scanned = JancodeLogs::where('jancode', $this->jancode->jancode)->count();

        return view('exports.scan_summary', [
            'jancode' => $this->jancode,
            'scanned' => $scanned
        ]);
    }

    public function title(): string
    {
        return 'Summary Report';
    }
}
