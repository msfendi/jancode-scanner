<?php

namespace App\Exports;

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
        return JancodeLogs::where('jancode', $this->jancode->jancode)
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
            'Color',
            'Description',
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
            $this->jancode->color,
            $this->jancode->description,
            $log->created_at->format('Y-m-d H:i:s'),
            $log->user ? $log->user->name : 'Unknown'
        ];
    }

    public function title(): string
    {
        return 'Scan History';
    }
}
