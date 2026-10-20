<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportBatch extends Model
{
    protected $table = 'import_batches';

    protected $fillable = [
        'session_id',
        'packing_list_id',
        'batch_number',
        'batch_name',
        'doc_type',
        'original_filename',
        'sheet_name',
        'supplier_name',
        'ocf_number',
        'cpo',
        'style_number',
        'destination',
        'warehouse',
        'group_key',
        'group_label',
        'revision_no',
        'is_active',
        'total_carton',
        'superseded_at',
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
        'import_date'   => 'date',
        'is_active'     => 'boolean',
        'superseded_at' => 'datetime',
    ];

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function packingList(): BelongsTo
    {
        return $this->belongsTo(PackingList::class, 'packing_list_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ImportSession::class, 'session_id');
    }

    public function jancodes(): HasMany
    {
        return $this->hasMany(Jancode::class, 'import_batch_id');
    }

    public function failedRows(): HasMany
    {
        return $this->hasMany(ImportFailedRow::class, 'import_batch_id');
    }

    public function scanBatches(): HasMany
    {
        return $this->hasMany(ScanBatch::class, 'import_batch_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Filter batch pada satu group packing list.
     *
     * SELALU dibatasi ke satu session (session_id null = data legacy tanpa
     * session). Dengan begitu packing list pada satu session tidak akan pernah
     * tercampur / memengaruhi session lain walau group_key-nya identik.
     */
    public function scopeForGroup(Builder $query, ?string $groupKey, ?int $sessionId = null): Builder
    {
        return $query
            ->where('group_key', $groupKey)
            ->where('session_id', $sessionId);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Kunci group packing list: STYLE | OCF/CPO | DESTINATION | WAREHOUSE.
     * Warehouse opsional sehingga bisa kosong.
     */
    public static function buildGroupKey(array $header): string
    {
        $parts = [
            strtoupper(trim((string) ($header['style'] ?? ''))),
            strtoupper(trim((string) ($header['ocf'] ?? $header['cpo'] ?? ''))),
            strtoupper(trim((string) ($header['destination'] ?? $header['country'] ?? ''))),
            strtoupper(trim((string) ($header['warehouse'] ?? ''))),
        ];

        return implode('|', $parts);
    }

    public static function buildGroupLabel(array $header): string
    {
        $style = strtoupper(trim((string) ($header['style'] ?? ''))) ?: '-';
        $cpo   = strtoupper(trim((string) ($header['ocf'] ?? $header['cpo'] ?? ''))) ?: '-';
        $dest  = strtoupper(trim((string) ($header['destination'] ?? $header['country'] ?? ''))) ?: '-';
        $wh    = strtoupper(trim((string) ($header['warehouse'] ?? '')));

        $label = "{$style} / {$cpo} / {$dest}";
        if ($wh !== '') {
            $label .= " / {$wh}";
        }

        return $label;
    }

    /**
     * Revisi aktif (final) untuk sebuah group packing list.
     *
     * ACTUAL adalah hasil akhir, jadi jika pada group sudah ada dokumen ACTUAL,
     * revisi ACTUAL terbaru yang menjadi aktif. Jika belum ada ACTUAL, revisi
     * PROFORMA terbaru yang menjadi aktif.
     */
    public static function activeForGroup(?string $groupKey, ?int $sessionId = null): ?self
    {
        if (!$groupKey) {
            return null;
        }

        $active = static::forGroup($groupKey, $sessionId)
            ->active()
            ->orderByDesc('revision_no')
            ->get();

        return $active->firstWhere('doc_type', 'actual') ?: $active->first();
    }

    /**
     * Hitung ulang revisi aktif sebuah group dan set flag is_active.
     * Dipakai setiap kali sebuah revisi baru diimport.
     */
    public static function recomputeActiveForGroup(?string $groupKey, ?int $sessionId = null): ?self
    {
        if (!$groupKey) {
            return null;
        }

        $batches = static::forGroup($groupKey, $sessionId)->orderByDesc('revision_no')->get();
        if ($batches->isEmpty()) {
            return null;
        }

        $winner = $batches->firstWhere('doc_type', 'actual') ?: $batches->first();

        foreach ($batches as $b) {
            $shouldBeActive = ($b->id === $winner->id);

            if ($shouldBeActive && !$b->is_active) {
                $b->update(['is_active' => true, 'superseded_at' => null]);
            } elseif (!$shouldBeActive && $b->is_active) {
                $b->update(['is_active' => false, 'superseded_at' => now()]);
            }
        }

        return $winner->refresh();
    }

    /**
     * Revisi berikutnya untuk sebuah group (dipakai saat import revisi baru).
     */
    public static function nextRevisionForGroup(?string $groupKey, ?int $sessionId = null): int
    {
        if (!$groupKey) {
            return 1;
        }

        return ((int) static::forGroup($groupKey, $sessionId)->max('revision_no')) + 1;
    }

    public function getDocTypeLabelAttribute(): string
    {
        return $this->doc_type === 'actual' ? 'ACTUAL' : 'PROFORMA';
    }

    public function getRevisionLabelAttribute(): string
    {
        $revision = (int) ($this->revision_no ?: 1);

        return $revision <= 1 ? 'Original' : 'Rev.' . $revision;
    }

    /**
     * Ringkasan singkat untuk ditampilkan di select2 / list.
     */
    public function getSummaryLabelAttribute(): string
    {
        $label = $this->group_label ?: $this->batch_name;
        $type  = strtoupper($this->doc_type ?: 'PROFORMA');

        return sprintf(
            '%s — %s %s (%d carton)',
            $label,
            $type,
            $this->revision_label,
            (int) $this->total_carton
        );
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
