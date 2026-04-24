<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ScanLogsExport implements WithMultipleSheets
{
    protected $jancode;

    public function __construct($jancode)
    {
        $this->jancode = $jancode;
    }

    public function sheets(): array
    {
        return [
            new ScanSummarySheet($this->jancode),
            new ScanHistorySheet($this->jancode),
        ];
    }
}
