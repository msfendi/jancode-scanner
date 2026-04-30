<?php

namespace App\Exports\JancodeSheet;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use App\Exports\JancodeSheet\ScanHistorySheet;
use App\Exports\JancodeSheet\ScanSummarySheet;

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
