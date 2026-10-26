<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Row gudang BR-07. */
    private const ROWS = ['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'];

    /** Hanya kolom c & d yang bertambah. */
    private const COLUMNS = ['c', 'd'];

    private const FROM_POSITION = 17;

    private const TO_POSITION = 19;

    /**
     * Kolom c & d bertambah dari 16 menjadi 19 posisi.
     * Menambah 8 row x 2 kolom x 3 posisi = 48 alamat baru (480 -> 528).
     */
    public function up(): void
    {
        if (!Schema::hasTable('pallet_address')) {
            return;
        }

        $existing = DB::table('pallet_address')->pluck('code')->flip();
        $now = now();
        $insert = [];

        foreach (self::ROWS as $row) {
            foreach (self::COLUMNS as $col) {
                for ($p = self::FROM_POSITION; $p <= self::TO_POSITION; $p++) {
                    $code = sprintf('%s-%s-%02d', $row, $col, $p);

                    if ($existing->has($code)) {
                        continue;
                    }

                    $insert[] = [
                        'row_label'   => $row,
                        'col_label'   => $col,
                        'position_no' => $p,
                        'code'        => $code,
                        'max_carton'  => 42,
                        'created_at'  => $now,
                        'updated_at'  => $now,
                    ];
                }
            }
        }

        foreach (array_chunk($insert, 100) as $chunk) {
            DB::table('pallet_address')->insert($chunk);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('pallet_address')) {
            return;
        }

        DB::table('pallet_address')
            ->whereIn('col_label', self::COLUMNS)
            ->whereBetween('position_no', [self::FROM_POSITION, self::TO_POSITION])
            ->delete();
    }
};
