<?php

namespace App\Exports\HangtagSheet;

use App\Models\HangtagLogs;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class HangtagScanSummarySheet implements FromView, WithTitle, ShouldAutoSize
{
    protected $hangtag;

    public function __construct($hangtag)
    {
        $this->hangtag = $hangtag;
    }

    public function view(): View
    {
        $scanned = HangtagLogs::where('barcode', $this->hangtag->barcode)->count();

        return view('exports.hangtag_scan_summary', [
            'hangtag' => $this->hangtag,
            'scanned' => $scanned
        ]);
    }

    public function title(): string
    {
        return 'Summary Report';
    }
}
