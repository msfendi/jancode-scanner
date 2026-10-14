<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JancodeLogs extends Model
{
    protected $table = 'jancode_logs';
    protected $fillable = [
        'jancode',
        'jancode_id',
        'scan_batch_id',
        'user_id',
        'is_submitted',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scanBatch()
    {
        return $this->belongsTo(ScanBatch::class, 'scan_batch_id');
    }
}
