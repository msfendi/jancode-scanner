<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Row gudang BR-07. */
    private const ROWS = ['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'];

    /** Kolom baru untuk mode 3 kolom per area: e (atas) = 14, f (bawah) = 19. */
    private const COLUMNS = ['e' => 14, 'f' => 19];

    /**
     * Menambah kolom e (14 posisi) & f (19 posisi) untuk tiap row.
     * 8 row x (14 + 19) posisi = 264 alamat baru (528 -> 792).
     *
     * Alamat e/f selalu dibuat, tetapi hanya ditampilkan bila row memakai
     * layout 3 kolom per area. Pada mode 2 kolom, e/f nonaktif namun datanya
     * tetap tersimpan agar tidak hilang saat layout diubah.
     */
    public function up(): void
    {
        if (! Schema::hasTable('pallet_address')) {
            return;
        }

        $existing = DB::table('pallet_address')->pluck('code')->flip();
        $now = now();
        $insert = [];

        foreach (self::ROWS as $row) {
            foreach (self::COLUMNS as $col => $positions) {
                for ($p = 1; $p <= $positions; $p++) {
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
        if (! Schema::hasTable('pallet_address')) {
            return;
        }

        DB::table('pallet_address')
            ->whereIn('col_label', array_keys(self::COLUMNS))
            ->delete();
    }
};
