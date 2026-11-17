<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Konfigurasi layout kolom per row pallet (B–I).
 *
 * Setiap row punya 2 area (atas & bawah); tiap area bisa berisi 2 atau 3
 * kolom. Default 2 kolom supaya tampilan yang sudah ada tidak berubah.
 */
return new class extends Migration
{
    private const ROWS = ['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'];

    public function up(): void
    {
        if (! Schema::hasTable('pallet_row_layouts')) {
            Schema::create('pallet_row_layouts', function (Blueprint $table) {
                $table->id();
                $table->string('row_label', 2)->unique();
                $table->unsignedTinyInteger('columns_per_area')->default(2);
                $table->timestamps();
            });
        }

        $now = now();
        $existingRows = DB::table('pallet_row_layouts')->pluck('row_label')->flip();

        foreach (self::ROWS as $row) {
            // Idempotent: jangan timpa pengaturan yang sudah ada.
            if ($existingRows->has($row)) {
                continue;
            }

            DB::table('pallet_row_layouts')->insert([
                'row_label'         => $row,
                'columns_per_area'  => 2,
                'created_at'        => $now,
                'updated_at'        => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pallet_row_layouts');
    }
};
