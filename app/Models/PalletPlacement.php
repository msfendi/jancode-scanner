<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Riwayat penempatan pallet ke lokasi.
 *
 * Satu baris = satu masa tinggal. `removed_at` NULL berarti masih aktif.
 */
class PalletPlacement extends Model
{
    protected $table = 'pallet_placements';

    public const REASON_PLACED   = 'placed';
    public const REASON_MOVED    = 'moved';
    public const REASON_FINISHED = 'finished';
    public const REASON_SWAPPED  = 'swapped';

    protected $fillable = [
        'pallet_id',
        'location_id',
        'placed_at',
        'removed_at',
        'placed_by',
        'removed_by',
        'reason',
    ];

    protected $casts = [
        'placed_at'  => 'datetime',
        'removed_at' => 'datetime',
    ];

    public function pallet()
    {
        return $this->belongsTo(Pallet::class, 'pallet_id');
    }

    public function location()
    {
        return $this->belongsTo(PalletAddress::class, 'location_id');
    }
}