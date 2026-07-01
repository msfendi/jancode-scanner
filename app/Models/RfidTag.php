<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RfidTag extends Model
{
    protected $table = 'rfid_tags';

    protected $fillable = [
        'epc',
        'antenna',
        'rssi',
        'read_at'
    ];

    protected $casts = [
        'read_at' => 'datetime'
    ];
}
