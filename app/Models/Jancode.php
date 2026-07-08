<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Jancode extends Model
{
    use HasFactory;

    protected $table = 'jancode';

    protected $fillable = [
        'jancode',
        'size',
        'qty',
        'destination',
        'buyer',
        'style',
        'cpo',
        'void',
    ];
}
