<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Konfigurasi layout kolom per row pallet.
 *
 * Setiap row (B–I) memiliki 2 area (atas & bawah) yang masing-masing berisi
 * 2 atau 3 kolom. Pengaturan disimpan per row supaya tampilan board bisa
 * berbeda antar row.
 *
 * Mode 3 kolom:
 *   ATAS   : a b e   (e = 14 posisi)
 *   BAWAH  : c d f   (c, d & f = 19 posisi)
 *
 * Mode 2 kolom:
 *   ATAS   : a b
 *   BAWAH  : c d
 *   (e & f nonaktif, alamatnya tetap ada di DB agar data tidak hilang)
 */
class PalletRowLayout extends Model
{
    /** Row gudang BR-07. */
    public const ROWS = ['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'];

    /** Default kolom per area. */
    public const DEFAULT_COLUMNS_PER_AREA = 2;

    /**
     * Jumlah posisi tiap kolom.
     * a/b/e mengikuti area depan (14), c/d/f mengikuti area belakang (19).
     */
    public const COLUMN_POSITIONS = [
        'a' => 14,
        'b' => 14,
        'c' => 19,
        'd' => 19,
        'e' => 14,
        'f' => 19,
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
     * @return array<int, array{key:string,label:string,columns:array<int,string>,positions:int}>
     */
    public static function areasForRow(string $rowLabel, ?array $layoutMap = null): array
    {
        $columnsPerArea = self::columnsPerArea($rowLabel, $layoutMap);

        if ($columnsPerArea === 3) {
            $top = ['a', 'b', 'e'];
            $bottom = ['c', 'd', 'f'];
        } else {
            $top = ['a', 'b'];
            $bottom = ['c', 'd'];
        }

        return [
            self::buildArea('top', 'AREA ATAS', $top),
            self::buildArea('bottom', 'AREA BAWAH', $bottom),
        ];
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
     * @return array{key:string,label:string,columns:array<int,string>,positions:int}
     */
    private static function buildArea(string $key, string $label, array $columns): array
    {
        $positions = 0;
        foreach ($columns as $column) {
            $positions = max($positions, (int) (self::COLUMN_POSITIONS[$column] ?? 19));
        }

        return [
            'key'       => $key,
            'label'     => $label,
            'columns'   => $columns,
            'positions' => $positions,
        ];
    }
}
