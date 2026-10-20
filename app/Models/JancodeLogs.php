<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JancodeLogs extends Model
{
    /** Karton masih ada di gudang (dihitung sebagai receive). */
    public const STATUS_IN = 'in';

    /** Karton sudah dikeluarkan / diambil packing (tidak dihitung receive). */
    public const STATUS_OUT = 'out';

    protected $table = 'jancode_logs';
    protected $fillable = [
        'jancode',
        'jancode_id',
        'packing_list_id',
        'import_batch_id',
        'session_id',
        'scan_batch_id',
        'user_id',
        'is_submitted',
        'status',
        'carton_out_id',
        'out_at',
    ];

    /**
     * Hanya log yang masih "ada" (belum keluar). Semua perhitungan receive
     * memakai scope ini supaya karton yang sudah di-scan out tidak ikut terhitung.
     */
    public function scopeInStock($query)
    {
        return $query->where('status', self::STATUS_IN);
    }

    public function scopeSubmitted($query)
    {
        return $query->where('is_submitted', 'true');
    }

    public function cartonOut()
    {
        return $this->belongsTo(CartonOut::class, 'carton_out_id');
    }

    /**
     * Baris master jancode (warna, size, style) untuk ditampilkan di log.
     */
    public function jancodeMaster()
    {
        return $this->belongsTo(Jancode::class, 'jancode_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scanBatch()
    {
        return $this->belongsTo(ScanBatch::class, 'scan_batch_id');
    }

    public function packingList()
    {
        return $this->belongsTo(PackingList::class, 'packing_list_id');
    }

    public function importBatch()
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }

    public function session()
    {
        return $this->belongsTo(ImportSession::class, 'session_id');
    }
}
