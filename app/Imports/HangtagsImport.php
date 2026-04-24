<?php

namespace App\Imports;

use App\Models\Hangtag;
use App\Models\Jancode;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class HangtagsImport implements ToModel, WithHeadingRow
{
    public function headingRow(): int
    {
        return 1;
    }

    // public function startRow(): int
    // {
    //     return 6; // Data starts after the header
    // }
    
    public function model(array $row)
    {
        return new Hangtag([
            'barcode' => $row['barcode'] ?? '',
            'color'   => $row['color'] ?? '',
            'qty'     => 0,
            'country' => $row['country'] ?? '',
            'size'    => $row['size'] ?? '',
            'buyer'   => $row['buyer'] ?? '',
        ]);
    }
}
