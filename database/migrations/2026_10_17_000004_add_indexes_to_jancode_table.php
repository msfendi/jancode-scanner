<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel `jancode` sebelumnya tidak memiliki index sama sekali, sehingga setiap
 * scan / lookup barcode melakukan full table scan. Pada data besar ini membuat
 * proses scan terasa lambat. Migrasi ini menambahkan index yang dipakai pada
 * alur scan dan pemuatan packing list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jancode', function (Blueprint $table) {
            $table->index('import_batch_id', 'jancode_import_batch_id_index');
            $table->index('jancode', 'jancode_code_index');
            $table->index('void', 'jancode_void_index');
            // Dipakai saat scan: cari barcode pada packing list tertentu.
            $table->index(['import_batch_id', 'jancode'], 'jancode_batch_code_index');
        });
    }

    public function down(): void
    {
        Schema::table('jancode', function (Blueprint $table) {
            $table->dropIndex('jancode_import_batch_id_index');
            $table->dropIndex('jancode_code_index');
            $table->dropIndex('jancode_void_index');
            $table->dropIndex('jancode_batch_code_index');
        });
    }
};
