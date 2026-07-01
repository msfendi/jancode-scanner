<?php

namespace App\Exports\HangtagSheet;

use Maatwebsite\Excel\Concerns\WithHeadings;

class HangtagTemplateExport implements WithHeadings
{
    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'barcode',
            'qty',
            'country',
            'color',
            'size',
            'buyer'
        ];
    }
}
