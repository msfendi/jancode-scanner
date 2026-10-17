<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScanBatch extends Model
{
    protected $table = 'scan_batches';

    protected $fillable = [
        'batch_no',
        'jancode_id',
        'import_batch_id',
        'user_id',
        'scanned_qty',
        'receive_qty',
        'balance',
        'total_qty',
        'notes',
    ];

    public function jancode()
    {
        return $this->belongsTo(Jancode::class, 'jancode_id');
    }

    public function importBatch()
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function logs()
    {
        return $this->hasMany(JancodeLogs::class, 'scan_batch_id');
    }
}
