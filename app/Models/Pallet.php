<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * Master pallet — objek fisik yang dipindah-pindah antar lokasi.
 *
 * Fase ini baru menyimpan identitas & QR. Relasi ke lokasi dan isi karton
 * ditambahkan pada fase penempatan.
 */
class Pallet extends Model
{
    use HasFactory;
    use SoftDeletes;

    /** Prefix kode pallet. */
    public const CODE_PREFIX = 'PLT-';

    /** Status yang dikenal. */
    public const STATUSES = [
        'available'   => 'Tersedia',
        'in_use'      => 'Dipakai',
        'maintenance' => 'Perawatan',
        'retired'     => 'Pensiun',
    ];

    protected $fillable = [
        'code',
        'status',
        'max_carton',
        'current_location_id',
        'placed_at',
        'zone',
        'notes',
    ];

    protected $casts = [
        'max_carton' => 'integer',
        'placed_at'  => 'datetime',
    ];

    /** Lokasi tempat pallet berdiri sekarang. */
    public function location()
    {
        return $this->belongsTo(PalletAddress::class, 'current_location_id');
    }

    /** Isi pallet (style/country/ocf + daftar karton). */
    public function assignment()
    {
        return $this->hasOne(PalletAssignment::class, 'pallet_id');
    }

    /** Riwayat penempatan. */
    public function placements()
    {
        return $this->hasMany(PalletPlacement::class, 'pallet_id')->orderByDesc('id');
    }

    public function isPlaced(): bool
    {
        return $this->current_location_id !== null;
    }

    /**
     * Kode pallet berikutnya, melanjutkan nomor terbesar yang ada.
     *
     * Dipakai di dalam transaksi + lock supaya dua admin yang menambah
     * bersamaan tidak mendapat kode yang sama.
     */
    public static function nextCode(): string
    {
        $max = static::withTrashed()
            ->selectRaw("MAX(CAST(SUBSTR(code, 5) AS INTEGER)) as max_no")
            ->value('max_no');

        return sprintf('%s%04d', self::CODE_PREFIX, ((int) $max) + 1);
    }

    /**
     * Buat pallet baru dengan kode otomatis.
     */
    public static function createNext(array $attributes = []): self
    {
        return DB::transaction(function () use ($attributes) {
            $pallet = new static($attributes);
            $pallet->code = static::nextCode();
            $pallet->save();

            return $pallet;
        });
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getQrPayloadAttribute(): string
    {
        return $this->code;
    }
}