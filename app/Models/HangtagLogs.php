<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HangtagLogs extends Model
{
    protected $table = 'hangtag_logs';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'barcode',
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
