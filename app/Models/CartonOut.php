<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Header batch KARTON KELUAR (scan out).
 *
 * Satu batch merekam pengambilan sejumlah karton dari sebuah packing list
 * (revisi aktif). Rincian per baris ada di CartonOutDetail.
 */
class CartonOut extends Model
{
    protected $table = 'carton_outs';

    protected $fillable = [
        'code',
        'session_id',
        'packing_list_id',
        'import_batch_id',
        'user_id',
        'total_carton',
        'notes',
    ];

    public function details()
    {
        return $this->hasMany(CartonOutDetail::class, 'carton_out_id');
    }

    public function session()
    {
        return $this->belongsTo(ImportSession::class, 'session_id');
    }

    public function packingList()
    {
        return $this->belongsTo(PackingList::class, 'packing_list_id');
    }

    public function importBatch()
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Kode batch keluar: BOUT-YYYYMMDD-0001 (increment harian).
     */
    public static function generateCode(): string
    {
        $prefix = 'BOUT-' . now()->format('Ymd') . '-';
        $last   = static::where('code', 'like', $prefix . '%')->orderByDesc('id')->first();
        $next   = $last ? ((int) substr($last->code, strlen($prefix)) + 1) : 1;

        return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
    }
}
