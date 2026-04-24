<?php

namespace App\Exports\HangtagSheet;

use App\Models\HangtagLogs;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class HangtagScanHistorySheet implements FromCollection, WithHeadings, WithMapping, WithTitle, ShouldAutoSize
{
    protected $hangtag;
    private $rowNumber = 0;

    public function __construct($hangtag)
    {
        $this->hangtag = $hangtag;
    }

    public function collection()
    {
        return HangtagLogs::where('barcode', $this->hangtag->barcode)
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function headings(): array
    {
        return [
            'No',
            'Barcode',
            'Color',
            'Country',
            'Size',
            'Buyer',
            'Scan Time'
        ];
    }

    public function map($log): array
    {
        $this->rowNumber++;

        return [
            $this->rowNumber,
            $this->hangtag->barcode,
            $this->hangtag->color,
            $this->hangtag->country,
            $this->hangtag->size,
            $this->hangtag->buyer,
            $log->created_at->format('Y-m-d H:i:s')
        ];
    }

    public function title(): string
    {
        return 'Scan History';
    }
}
