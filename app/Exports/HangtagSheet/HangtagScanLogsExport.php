<?php

namespace App\Exports\HangtagSheet;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class HangtagScanLogsExport implements WithMultipleSheets
{
    protected $hangtag;

    public function __construct($hangtag)
    {
        $this->hangtag = $hangtag;
    }

    public function sheets(): array
    {
        return [
            new HangtagScanSummarySheet($this->hangtag),
            new HangtagScanHistorySheet($this->hangtag),
        ];
    }
}
