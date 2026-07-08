<?php

namespace App\Imports;

use App\Models\Jancode;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;

class JancodesNewFormatImport implements ToModel, WithStartRow, SkipsEmptyRows, WithCalculatedFormulas
{
    /**
     * Data starts from row 14 (new Excel format).
     */
    public function startRow(): int
    {
        return 14;
    }

    public function model(array $row)
    {
        // Skip the row if the jancode column is completely empty
        if (!isset($row[6]) || trim($row[6]) === '') {
            return null;
        }

        return new Jancode([
            'jancode'     => $row[6] ?? '',
            'size'        => $row[9] ?? '',
            'qty'         => (int) ($row[15] ?? 0),
            'destination' => $row[3] ?? '',
            'buyer'       => 'MUJI',
            'style'       => $row[4] ?? '',
            'cpo'         => 'SAMPLE',
            'color'       => $row[8] ?? '',
        ]);
    }
}
