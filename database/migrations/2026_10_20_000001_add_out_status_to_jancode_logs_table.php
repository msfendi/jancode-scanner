<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * KARTON KELUAR (scan out).
 *
 * Karton yang sudah discan (receive) atau bahkan sudah ditempatkan di pallet
 * bisa diambil kembali oleh packing. Alih-alih menghapus log scan, tiap baris
 * log ditandai status = 'in' | 'out' supaya riwayat scan tetap utuh dan bisa
 * diaudit. Semua perhitungan receive hanya menghitung baris status = 'in'.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('jancode_logs', 'status')) {
            Schema::table('jancode_logs', function (Blueprint $table) {
                $table->string('status', 10)->default('in')->index();   // in | out
                $table->unsignedBigInteger('carton_out_id')->nullable()->index();
                $table->timestamp('out_at')->nullable();
            });
        }

        // Baris lama dianggap masih "in".
        DB::table('jancode_logs')->whereNull('status')->update(['status' => 'in']);
    }

    public function down(): void
    {
        try {
            Schema::table('jancode_logs', function (Blueprint $table) {
                $table->dropIndex(['status']);
                $table->dropIndex(['carton_out_id']);
                $table->dropColumn(['status', 'carton_out_id', 'out_at']);
            });
        } catch (\Throwable $e) {
            // abaikan
        }
    }
};
