<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Set semua row (B–I) ke layout 3 kolom per area.
 *
 * Sebelumnya hanya D/F/H/I (sebagian) memakai 3 kolom. Dengan layout seragam,
 * kolom e & f aktif di seluruh row sehingga total lokasi menjadi 792
 * (8 row x 6 kolom). Alamat e/f sudah ada di DB sejak
 * `2026_11_17_000002_add_ef_pallet_positions`; migration ini tidak membuat
 * alamat baru dan tidak menyentuh `pallet_assignment`.
 */
return new class extends Migration
{
    private const ROWS = ['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'];

    public function up(): void
    {
        if (! Schema::hasTable('pallet_row_layouts')) {
            return;
        }

        $now = now();

        foreach (self::ROWS as $row) {
            $updated = DB::table('pallet_row_layouts')
                ->where('row_label', $row)
                ->update([
                    'columns_per_area' => 3,
                    'updated_at'       => $now,
                ]);

            if ($updated === 0) {
                // Row belum punya baris konfigurasi -> buat.
                DB::table('pallet_row_layouts')->insert([
                    'row_label'        => $row,
                    'columns_per_area' => 3,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Tidak dikembalikan ke 2 kolom: layout seragam adalah target akhir.
        // Kembalikan manual lewat UI Manage Pallet bila diperlukan.
    }
};