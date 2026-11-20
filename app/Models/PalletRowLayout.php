<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Konfigurasi layout line per row pallet.
 *
 * Setiap row (B–I) punya 3 line: a, b, c. Tiap line berisi 33 posisi
 * menerus — 01–14 di area depan, 15–33 di area belakang (dipisah garis,
 * sisi belakang tanpa teks).
 *
 * Pengaturan per row menentukan berapa line yang dipakai:
 *   mode 3 : line a, b, c  (99 slot/row)
 *   mode 2 : line a, b     (66 slot/row, line c disembunyikan)
 * Data line yang disembunyikan tetap ada di DB agar tidak hilang.
 */
class PalletRowLayout extends Model
{
    /** Row gudang BR-07. */
    public const ROWS = ['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'];

    /** Default line aktif per row. */
    public const DEFAULT_COLUMNS_PER_AREA = 2;

    /** Nama line (dulu kolom). */
    public const LINES = ['a', 'b', 'c'];

    /** Batas posisi area depan. Posisi di atas ini masuk area belakang. */
    public const FRONT_POSITIONS = 14;

    /** Jumlah posisi maksimum per line (depan + belakang). */
    public const TOTAL_POSITIONS = 33;

    /** Jumlah posisi tiap line. */
    public const COLUMN_POSITIONS = [
        'a' => self::TOTAL_POSITIONS,
        'b' => self::TOTAL_POSITIONS,
        'c' => self::TOTAL_POSITIONS,
    ];

    protected $table = 'pallet_row_layouts';

    protected $fillable = [
        'row_label',
        'columns_per_area',
    ];

    protected $casts = [
        'columns_per_area' => 'integer',
    ];

    /**
     * Peta row => jumlah kolom per area (2/3).
     *
     * @return array<string,int>
     */
    public static function layoutMap(): array
    {
        $map = array_fill_keys(self::ROWS, self::DEFAULT_COLUMNS_PER_AREA);

        try {
            foreach (static::query()->get() as $layout) {
                $row = strtoupper((string) $layout->row_label);
                if (isset($map[$row])) {
                    $map[$row] = ((int) $layout->columns_per_area === 3) ? 3 : 2;
                }
            }
        } catch (\Throwable $e) {
            // Tabel belum ada (mis. saat migrasi awal) -> pakai default.
        }

        return $map;
    }

    public static function flushCache(): void
    {
        // Tidak ada cache statis; method dipertahankan agar pemanggil lama
        // tetap kompatibel dan layout selalu terbaca langsung dari DB.
    }

    public static function columnsPerArea(string $rowLabel, ?array $layoutMap = null): int
    {
        $layoutMap ??= self::layoutMap();
        $row = strtoupper($rowLabel);

        return (int) ($layoutMap[$row] ?? self::DEFAULT_COLUMNS_PER_AREA) === 3 ? 3 : 2;
    }

    /**
     * Definisi area untuk satu row.
     *
     * Area depan memakai posisi 1..14, area belakang 15..33 pada line yang
     * sama. Sisi belakang sengaja tanpa label teks (hanya garis pembatas).
     *
     * @return array<int, array{key:string,label:string,columns:array<int,string>,positions:int,from:int,to:int}>
     */
    public static function areasForRow(string $rowLabel, ?array $layoutMap = null): array
    {
        $lines = self::linesForRow($rowLabel, $layoutMap);

        return [
            self::buildArea('depan', 'DEPAN', $lines, 1, self::FRONT_POSITIONS),
            self::buildArea('belakang', '', $lines, self::FRONT_POSITIONS + 1, self::TOTAL_POSITIONS),
        ];
    }

    /**
     * Line yang aktif pada sebuah row.
     *
     * @return array<int,string>
     */
    public static function linesForRow(string $rowLabel, ?array $layoutMap = null): array
    {
        return self::columnsPerArea($rowLabel, $layoutMap) === 3
            ? self::LINES
            : array_slice(self::LINES, 0, 2);
    }

    /**
     * Pemetaan line aktif tiap row (dipakai untuk menandai sel yang aktif).
     *
     * @return array<string, array<int,string>>
     */
    public static function linesMap(?array $layoutMap = null): array
    {
        $layoutMap ??= self::layoutMap();
        $map = [];

        foreach (self::ROWS as $row) {
            $map[$row] = self::linesForRow($row, $layoutMap);
        }

        return $map;
    }

    /**
     * Definisi area untuk semua row.
     *
     * @return array<string, array<int, array>>
     */
    public static function areasMap(?array $layoutMap = null): array
    {
        $layoutMap ??= self::layoutMap();
        $map = [];

        foreach (self::ROWS as $row) {
            $map[$row] = self::areasForRow($row, $layoutMap);
        }

        return $map;
    }

    /**
     * Kolom yang aktif untuk tiap row sesuai layout.
     *
     * @return array<string, array<int,string>>
     */
    public static function activeColumnsMap(?array $layoutMap = null): array
    {
        $layoutMap ??= self::layoutMap();
        $map = [];

        foreach (self::ROWS as $row) {
            $columns = [];
            foreach (self::areasForRow($row, $layoutMap) as $area) {
                $columns = array_merge($columns, $area['columns']);
            }
            $map[$row] = array_values(array_unique($columns));
        }

        return $map;
    }

    /**
     * @param  array<int,string>  $columns
     * @return array{key:string,label:string,columns:array<int,string>,positions:int,from:int,to:int}
     */
    private static function buildArea(string $key, string $label, array $columns, int $from, int $to): array
    {
        return [
            'key'       => $key,
            'label'     => $label,
            'columns'   => $columns,
            'positions' => $to,
            'from'      => $from,
            'to'        => $to,
        ];
    }
}
