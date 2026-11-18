<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Row gudang BR-07. */
    private const ROWS = ['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'];

    /** Kolom e hanya dipakai 14 posisi (area atas). */
    private const E_MAX_POSITION = 14;

    /**
     * Koreksi jumlah posisi kolom e menjadi 14.
     *
     * Migration `2026_11_17_000002_add_ef_pallet_positions` versi awal membuat
     * kolom e sebanyak 19 posisi. Layout final memakai e di area atas sehingga
     * posisinya 14 (sama seperti a & b). Migration ini menghapus kelebihan
     * alamat e-15..e-19 yang belum dipakai pallet, dan aman dijalankan berulang.
     */
    public function up(): void
    {
        if (! Schema::hasTable('pallet_address') || ! Schema::hasTable('pallet_assignment')) {
            return;
        }

        $ids = DB::table('pallet_address')
            ->whereIn('row_label', self::ROWS)
            ->where('col_label', 'e')
            ->where('position_no', '>', self::E_MAX_POSITION)
            ->pluck('id')
            ->all();

        foreach (array_chunk($ids, 100) as $chunk) {
            $used = DB::table('pallet_assignment')
                ->whereIn('pallet_address_id', $chunk)
                ->pluck('pallet_address_id')
                ->all();

            $deletable = array_values(array_diff($chunk, $used));

            if (! empty($deletable)) {
                DB::table('pallet_address')->whereIn('id', $deletable)->delete();
            }
        }
    }

    public function down(): void
    {
        // Tidak membuat ulang alamat e-15..e-19; layout final memakai 14 posisi.
    }
};
