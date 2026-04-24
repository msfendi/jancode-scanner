<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HangtagLogs extends Model
{
    protected $table = 'hangtag_logs';

    protected $fillable = [
        'barcode',
    ];
}
