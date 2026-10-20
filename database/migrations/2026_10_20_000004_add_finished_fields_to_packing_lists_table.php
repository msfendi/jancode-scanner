<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Status FINISHED untuk packing list + snapshot hasil akhir.
 *
 * Snapshot disimpan saat operator menekan Finish. Dengan begitu report PDF /
 * Excel tetap menampilkan kondisi "semua balance 0" pada saat finish, meskipun
 * setelah itu ada carton out (yang secara alami membuat balance master kembali
 * bertambah).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packing_lists', function (Blueprint $table) {
            if (!Schema::hasColumn('packing_lists', 'finished_at')) {
                $table->dateTime('finished_at')->nullable();
            }
            if (!Schema::hasColumn('packing_lists', 'finished_by')) {
                $table->unsignedBigInteger('finished_by')->nullable();
            }
            if (!Schema::hasColumn('packing_lists', 'finished_import_batch_id')) {
                $table->unsignedBigInteger('finished_import_batch_id')->nullable();
                $table->index('finished_import_batch_id', 'packing_lists_finished_batch_index');
            }
            if (!Schema::hasColumn('packing_lists', 'finished_snapshot')) {
                $table->longText('finished_snapshot')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('packing_lists', function (Blueprint $table) {
            if (Schema::hasColumn('packing_lists', 'finished_import_batch_id')) {
                $table->dropIndex('packing_lists_finished_batch_index');
            }
            foreach (['finished_snapshot', 'finished_import_batch_id', 'finished_by', 'finished_at'] as $column) {
                if (Schema::hasColumn('packing_lists', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
