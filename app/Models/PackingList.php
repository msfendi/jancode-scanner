<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Entitas PACKING LIST yang stabil antar revisi.
 *
 * group_key = STYLE | OCF/CPO | DESTINATION | WAREHOUSE. Beberapa revisi
 * (ImportBatch) dengan group_key yang sama membentuk satu packing list ini.
 * Scan log dan penempatan pallet terhubung ke id packing list, sehingga barcode
 * yang sama pada packing list berbeda tetap bisa dibedakan.
 */
class PackingList extends Model
{
    protected $table = 'packing_lists';

    protected $fillable = [
        'session_id',
        'group_key',
        'group_label',
        'style_number',
        'ocf_number',
        'destination',
        'warehouse',
        'supplier_name',
        'active_import_batch_id',
    ];

    public function importBatches(): HasMany
    {
        return $this->hasMany(ImportBatch::class, 'packing_list_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ImportSession::class, 'session_id');
    }

    public function activeImportBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'active_import_batch_id');
    }

    public function jancodes(): HasMany
    {
        return $this->hasMany(Jancode::class, 'packing_list_id');
    }

    public function scanBatches(): HasMany
    {
        return $this->hasMany(ScanBatch::class, 'packing_list_id');
    }

    /**
     * Cari packing list berdasarkan (session, group_key), buat jika belum ada.
     * Identitas packing list = session + group_key.
     */
    public static function findOrCreateForGroupKey(?string $groupKey, ?int $sessionId = null): ?self
    {
        if (!$groupKey) {
            return null;
        }

        return static::firstOrCreate([
            'session_id' => $sessionId,
            'group_key'  => $groupKey,
        ]);
    }

    /**
     * Perbarui metadata + revisi aktif packing list dari sebuah ImportBatch.
     */
    public function syncFromBatch(ImportBatch $batch, ?int $activeBatchId = null): void
    {
        $this->update([
            'group_label'            => $batch->group_label ?: $this->group_label,
            'style_number'           => $batch->style_number ?: $this->style_number,
            'ocf_number'             => $batch->ocf_number ?: $this->ocf_number,
            'destination'            => $batch->destination ?: $this->destination,
            'warehouse'              => $batch->warehouse ?: $this->warehouse,
            'supplier_name'          => $batch->supplier_name ?: $this->supplier_name,
            'active_import_batch_id' => $activeBatchId ?? $this->active_import_batch_id,
        ]);
    }

    public function getSummaryLabelAttribute(): string
    {
        return $this->group_label ?: $this->group_key;
    }
}
