<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportFailedRow extends Model
{
    protected $table = 'import_failed_rows';

    protected $fillable = [
        'import_batch_id',
        'source_row',
        'reason_code',
        'reason_label',
        'jan_code',
        'color',
        'size',
        'keterangan',
        'total_carton',
        'carton_merged',
        'raw_data',
        'notes',
    ];

    protected $casts = [
        'carton_merged' => 'boolean',
        'raw_data'      => 'array',
    ];

    // Mapping reason code ke label yang mudah dibaca
    public static array $reasonLabels = [
        'MERGED_CARTON' => 'Broken: Kolom qty carton di-merge (carton dipakai bersama beberapa baris)',
        'NO_JAN'        => 'JAN Code tidak valid (bukan 13 digit)',
        'NO_CARTON'     => 'Total carton kosong atau 0',
        'NO_SIZE'       => 'Tidak ada breakdown size yang terisi',
        'MULTI_SIZE'    => 'Lebih dari satu size terisi (tidak deterministik)',
        'ASSORT'        => 'Baris ASSORT PACK, tidak diimport',
        'OTHER'         => 'Keterangan bukan SOLID',
        'SUMMARY_ROW'   => 'Baris ringkasan/total (TOTAL, QTY, GRAND TOTAL)',
    ];

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }

    public function getReasonLabelAttribute(): string
    {
        return static::$reasonLabels[$this->reason_code] ?? $this->reason_code ?? 'Unknown';
    }
}
