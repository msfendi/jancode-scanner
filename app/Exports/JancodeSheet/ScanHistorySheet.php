<?php

namespace App\Exports\JancodeSheet;

use App\Models\JancodeLogs;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class ScanHistorySheet implements FromCollection, WithHeadings, WithMapping, WithTitle, ShouldAutoSize
{
    protected $jancode;
    private $rowNumber = 0;

    public function __construct($jancode)
    {
        $this->jancode = $jancode;
    }

    public function collection()
    {
        return JancodeLogs::where('jancode_id', $this->jancode->id)
            ->with('user')
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function headings(): array
    {
        return [
            'No',
            'Jancode',
            'Size',
            'Destination',
            'Buyer',
            'Style',
            'CPO',
            'Scan Time',
            'Scanned By'
        ];
    }

    public function map($log): array
    {
        $this->rowNumber++;

        return [
            $this->rowNumber,
            $this->jancode->jancode,
            $this->jancode->size,
            $this->jancode->destination,
            $this->jancode->buyer,
            $this->jancode->style,
            $this->jancode->cpo,
            $log->created_at->format('Y-m-d H:i:s'),
            $log->user ? $log->user->name : 'Unknown'
        ];
    }

    public function title(): string
    {
        return 'Scan History';
    }
}
