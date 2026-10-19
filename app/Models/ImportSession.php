<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * SESSION sebagai induk pekerjaan packing list.
 *
 * Satu session mengikat satu kombinasi style | OCF | destination | warehouse.
 * Semua import (revisi PROFORMA/ACTUAL), scan, dan pallet di dalamnya berada
 * pada session ini. Kode dibuat otomatis: IMP-YYYYMMDD-HHMMSS-001.
 */
class ImportSession extends Model
{
    public const SESSION_KEY = 'import_session_id';

    protected $table = 'import_sessions';

    protected $fillable = [
        'code',
        'notes',
        'group_key',
        'style_number',
        'ocf_number',
        'destination',
        'warehouse',
        'supplier_name',
        'status',
        'user_id',
        'started_at',
        'closed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'closed_at'  => 'datetime',
    ];

    // ── Relations ─────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function packingLists(): HasMany
    {
        return $this->hasMany(PackingList::class, 'session_id');
    }

    public function importBatches(): HasMany
    {
        return $this->hasMany(ImportBatch::class, 'session_id');
    }

    public function jancodeLogs(): HasMany
    {
        return $this->hasMany(JancodeLogs::class, 'session_id');
    }

    public function scanBatches(): HasMany
    {
        return $this->hasMany(ScanBatch::class, 'session_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    // ── Session aktif (dipilih user) ─────────────────────────────────────

    public static function current(): ?self
    {
        $id = session(self::SESSION_KEY);
        if (!$id) {
            return null;
        }

        return static::find($id);
    }

    public static function currentId(): ?int
    {
        return static::current()?->id;
    }

    public static function setCurrent(?int $id): void
    {
        if ($id) {
            session([self::SESSION_KEY => $id]);
        } else {
            session()->forget(self::SESSION_KEY);
        }

        // Lock scan tidak boleh menyeberang session.
        session()->forget('scan_import_batch_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Kode session otomatis: IMP-YYYYMMDD-HHMMSS-001 (increment global).
     */
    public static function generateCode(): string
    {
        $prefix = 'IMP-' . now()->format('Ymd-His') . '-';

        $max = static::query()
            ->pluck('code')
            ->map(fn ($code) => (int) substr((string) $code, -3))
            ->max() ?? 0;

        return $prefix . str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    /**
     * Apakah file dengan group_key ini boleh masuk ke session?
     * Jika session belum punya group_key, file pertama yang menentukannya.
     */
    public function acceptsGroup(?string $groupKey): bool
    {
        return !$this->group_key || $this->group_key === $groupKey;
    }

    /**
     * Ikat group_key + metadata header saat import pertama.
     */
    public function bindGroup(string $groupKey, array $header, ?string $supplierName = null): void
    {
        if ($this->group_key) {
            return;
        }

        $this->update([
            'group_key'    => $groupKey,
            'style_number' => $header['style'] ?? null,
            'ocf_number'   => $header['ocf'] ?? $header['cpo'] ?? null,
            'destination'  => $header['destination'] ?? $header['country'] ?? null,
            'warehouse'    => $header['warehouse'] ?? null,
            'supplier_name'=> $supplierName ?? ($header['buyer'] ?? null),
        ]);
    }

    public function close(): void
    {
        $this->update([
            'status'    => 'closed',
            'closed_at' => now(),
        ]);

        if ((int) session(self::SESSION_KEY) === (int) $this->id) {
            static::setCurrent(null);
        }
    }

    public function getLabelAttribute(): string
    {
        return $this->code;
    }

    public function getGroupLabelAttribute(): string
    {
        if (!$this->group_key) {
            return 'Belum ada packing list';
        }

        $parts = array_filter([
            $this->style_number,
            $this->ocf_number,
            $this->destination,
            $this->warehouse,
        ], fn ($v) => $v !== null && $v !== '');

        return implode(' / ', $parts) ?: $this->group_key;
    }
}
