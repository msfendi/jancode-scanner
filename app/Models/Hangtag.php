<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Hangtag extends Model
{
    protected $table = 'hangtags';

    protected $fillable = [
        'barcode',
        'qty',
        'country',
        'color',
        'size',
        'buyer',
    ];
}