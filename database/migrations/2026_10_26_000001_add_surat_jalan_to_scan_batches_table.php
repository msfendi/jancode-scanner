<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kode surat jalan per submit (partial scan sesuai kedatangan).
 * Setiap batch scan = satu kedatangan, sehingga pengiriman bisa dilacak.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('scan_batches') || Schema::hasColumn('scan_batches', 'surat_jalan')) {
            return;
        }

        Schema::table('scan_batches', function (Blueprint $table) {
            $table->string('surat_jalan', 100)->nullable()->after('notes');
            $table->index('surat_jalan');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('scan_batches') || !Schema::hasColumn('scan_batches', 'surat_jalan')) {
            return;
        }

        Schema::table('scan_batches', function (Blueprint $table) {
            $table->dropIndex(['surat_jalan']);
            $table->dropColumn('surat_jalan');
        });
    }
};
