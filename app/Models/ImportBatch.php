<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportBatch extends Model
{
    protected $table = 'import_batches';

    protected $fillable = [
        'batch_number',
        'batch_name',
        'original_filename',
        'supplier_name',
        'ocf_number',
        'cpo',
        'import_date',
        'status',
        'total_rows',
        'valid_rows',
        'invalid_rows',
        'total_items',
        'processed_items',
        'notes',
        'import_notes',
        'imported_by',
    ];

    protected $casts = [
        'import_date' => 'date',
    ];

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function jancodes(): HasMany
    {
        return $this->hasMany(Jancode::class, 'import_batch_id');
    }

    public function failedRows(): HasMany
    {
        return $this->hasMany(ImportFailedRow::class, 'import_batch_id');
    }

    /**
     * Generate batch number otomatis: BATCH-YYYYMMDD-XXX
     */
    public static function generateBatchNumber(): string
    {
        $prefix = 'BATCH-' . now()->format('Ymd') . '-';
        $last = static::where('batch_number', 'like', $prefix . '%')
            ->orderByDesc('batch_number')
            ->value('batch_number');

        $seq = $last ? ((int) substr($last, -3)) + 1 : 1;
        return $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);
    }
}
