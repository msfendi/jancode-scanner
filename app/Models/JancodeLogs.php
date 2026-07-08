<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JancodeLogs extends Model
{
    protected $table = 'jancode_logs';
    protected $primaryKey = null;
    public $incrementing = false;

    protected $fillable = [
        'jancode',
        'jancode_id',
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
