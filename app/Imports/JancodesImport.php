<?php

namespace App\Imports;

use App\Models\Jancode;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class JancodesImport implements ToModel, WithHeadingRow
{
    public function headingRow(): int
    {
        return 5;
    }

    // public function startRow(): int
    // {
    //     return 6; // Data starts after the header
    // }
    
    public function model(array $row)
    {
        return new Jancode([
            'jancode'     => $row['jan'] ?? '',
            'size'        => $row['size'] ?? '',
            'qty'         =>  0,
            'country'     => $row['country'] ?? '',
            'color'       => $row['color'] ?? '',
            'description' => $row['descriptionen'] ?? '',
        ]);
    }
}
