<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabel riwayat upload packing list
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_number')->unique(); // e.g. BATCH-20261014-001
            $table->string('batch_name')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('supplier_name')->nullable();
            $table->string('ocf_number')->nullable();
            $table->string('cpo')->nullable();
            $table->date('import_date')->nullable();
            $table->string('status', 30)->default('pending');
            $table->integer('total_rows')->default(0);
            $table->integer('valid_rows')->default(0);
            $table->integer('invalid_rows')->default(0);
            $table->integer('total_items')->default(0);
            $table->integer('processed_items')->default(0);
            $table->text('notes')->nullable();
            $table->text('import_notes')->nullable();
            $table->unsignedBigInteger('imported_by')->nullable();
            $table->timestamps();

            $table->foreign('imported_by')->references('id')->on('users')->nullOnDelete();
        });

        // Tabel baris gagal diimport (broken/skipped rows)
        Schema::create('import_failed_rows', function (Blueprint $table) {
            $table->id();
            $table->string('source_row', 50)->nullable();       // nomor baris di Excel, e.g. "14-15"
            $table->string('reason_code')->nullable();           // MERGED_CARTON, NO_JAN, ASSORT, dll
            $table->string('reason_label')->nullable();          // label deskriptif
            $table->text('jan_code')->nullable();                // text untuk comma/json gabungan
            $table->text('color')->nullable();                   // text untuk comma/json gabungan
            $table->text('size')->nullable();                    // text untuk comma/json gabungan
            $table->string('keterangan')->nullable();
            $table->integer('total_carton')->nullable();
            $table->boolean('carton_merged')->default(false);
            $table->text('raw_data')->nullable();            // JSON raw data baris
            $table->text('notes')->nullable();               // notes tambahan
            $table->timestamps();

            $table->foreign('import_batch_id')->references('id')->on('import_batches')->cascadeOnDelete();
        });

        // Tambah kolom import_batch_id ke tabel jancode
        if (Schema::hasTable('jancode') && !Schema::hasColumn('jancode', 'import_batch_id')) {
            Schema::table('jancode', function (Blueprint $table) {
                $table->unsignedBigInteger('import_batch_id')->nullable()->after('id');
                $table->foreign('import_batch_id')->references('id')->on('import_batches')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('jancode') && Schema::hasColumn('jancode', 'import_batch_id')) {
            Schema::table('jancode', function (Blueprint $table) {
                $table->dropForeign(['import_batch_id']);
                $table->dropColumn('import_batch_id');
            });
        }
        Schema::dropIfExists('import_failed_rows');
        Schema::dropIfExists('import_batches');
    }
};
