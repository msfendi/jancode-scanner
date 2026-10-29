<?php

namespace App\Services;

use App\Models\PalletColorRule;
use Illuminate\Support\Collection;

/**
 * Resolver warna pallet berdasarkan negara, buyer, dan status isi.
 *
 * Urutan pencocokan:
 *   1. country + buyer persis
 *   2. country (buyer null / tidak peduli)
 *   3. buyer catch-all (mis. semua MUJI di luar Japan/China/Taiwan)
 *   4. fallback global (buyer & country null)
 */
class PalletColorService
{
    private static ?Collection $rulesCache = null;

    public function rules(): Collection
    {
        if (self::$rulesCache === null) {
            self::$rulesCache = PalletColorRule::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        }

        return self::$rulesCache;
    }

    public static function flush(): void
    {
        self::$rulesCache = null;
    }

    /**
     * @return array{status:string,color:string,text_color:string,text_shadow:string,rule_id:?int}
     */
    public function resolve(?string $country, ?string $buyer, int $cartons, int $maxCarton = 42): array
    {
        $maxCarton = max(1, $maxCarton);
        $status = $cartons <= 0 ? 'empty' : ($cartons >= $maxCarton ? 'full' : 'partial');

        $rules = $this->rules()->where('status', $status)->values();
        $countryKey = $this->normalizeCountry($country);
        $buyerKey = $this->normalizeBuyer($buyer);

        $rule = null;

        if ($status === 'empty') {
            $rule = $rules->first(fn ($r) => $r->buyer === null && $r->country === null);
        } else {
            $rule = $rules->first(fn ($r) => $r->country !== null
                    && $r->country === $countryKey
                    && $r->buyer !== null
                    && $r->buyer === $buyerKey)
                ?? $rules->first(fn ($r) => $r->country !== null
                    && $r->country === $countryKey
                    && $r->buyer === null)
                ?? $rules->first(fn ($r) => $r->country !== null
                    && $r->country === $countryKey)
                ?? $rules->first(fn ($r) => $r->country === null
                    && $r->buyer !== null
                    && $r->buyer === $buyerKey)
                ?? $rules->first(fn ($r) => $r->country === null && $r->buyer === null);
        }

        $color = $rule?->color ?: '#e5e7eb';
        $textColor = $rule?->text_color ?: '#374151';

        return [
            'status'      => $status,
            'color'       => $color,
            'text_color'  => $textColor,
            'text_shadow' => $this->textShadowFor($textColor),
            'rule_id'     => $rule?->id,
        ];
    }

    /**
     * Data untuk legend board (kelompok negara + pallet kosong).
     *
     * @return array{groups:array<int,array<string,mixed>>,empty:?PalletColorRule}
     */
    public function legend(): array
    {
        $groups = $this->rules()
            ->whereIn('status', ['full', 'partial'])
            ->groupBy('group_key')
            ->map(function (Collection $items) {
                $first = $items->first();

                return [
                    'key'     => $first->group_key,
                    'label'   => $first->group_label,
                    'full'    => $items->firstWhere('status', 'full'),
                    'partial' => $items->firstWhere('status', 'partial'),
                ];
            })
            ->sortBy(fn ($g) => $g['partial']?->sort_order ?? $g['full']?->sort_order ?? 999)
            ->values()
            ->all();

        return [
            'groups' => $groups,
            'empty'  => $this->rules()->firstWhere('status', 'empty'),
        ];
    }

    public function normalizeCountry(?string $country): ?string
    {
        $value = strtoupper(trim((string) $country));

        if ($value === '') {
            return null;
        }

        if (str_contains($value, 'JAPAN') || preg_match('/\bJPN?\b/', $value)) {
            return 'JAPAN';
        }

        if (str_contains($value, 'TAIWAN') || preg_match('/\bTW\b/', $value)) {
            return 'TAIWAN';
        }

        // Contoh data: "SHANGHAI D7-CN", "CHINA", "CN".
        if (str_contains($value, 'CHINA')
            || str_contains($value, 'SHANGHAI')
            || preg_match('/(^|[^A-Z])CN([^A-Z]|$)/', $value)) {
            return 'CHINA';
        }

        return 'OTHER';
    }

    public function normalizeBuyer(?string $buyer): ?string
    {
        $value = strtoupper(trim((string) $buyer));

        if ($value === '') {
            return null;
        }

        return str_contains($value, 'MUJI') ? 'MUJI' : $value;
    }

    private function textShadowFor(string $textColor): string
    {
        $hex = ltrim($textColor, '#');

        if (strlen($hex) !== 6) {
            return 'none';
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        return $luminance > 0.65 ? '0 1px 2px rgba(0, 0, 0, 0.42)' : 'none';
    }
}
