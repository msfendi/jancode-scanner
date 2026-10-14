<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabel riwayat batch scan
        if (!Schema::hasTable('scan_batches')) {
            Schema::create('scan_batches', function (Blueprint $table) {
                $table->id();
                $table->string('batch_no')->unique(); // e.g. BSCAN-20261014-0001
                $table->unsignedBigInteger('jancode_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->integer('scanned_qty')->default(0);  // Jumlah karton di-scan pada batch ini
                $table->integer('receive_qty')->default(0);  // Akumulasi received saat submit
                $table->integer('balance')->default(0);      // Sisa balance saat submit
                $table->integer('total_qty')->default(0);    // Total master PL saat submit
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('jancode_id')->references('id')->on('jancode')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        // 2. Hubungkan jancode_logs ke scan_batches
        if (Schema::hasTable('jancode_logs') && !Schema::hasColumn('jancode_logs', 'scan_batch_id')) {
            Schema::table('jancode_logs', function (Blueprint $table) {
                $table->unsignedBigInteger('scan_batch_id')->nullable()->after('jancode_id');
                $table->foreign('scan_batch_id')->references('id')->on('scan_batches')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('jancode_logs') && Schema::hasColumn('jancode_logs', 'scan_batch_id')) {
            Schema::table('jancode_logs', function (Blueprint $table) {
                $table->dropForeign(['scan_batch_id']);
                $table->dropColumn('scan_batch_id');
            });
        }

        Schema::dropIfExists('scan_batches');
    }
};
