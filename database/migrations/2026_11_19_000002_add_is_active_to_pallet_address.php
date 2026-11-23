<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Status aktif lokasi (pallet_address).
 *
 * Lokasi nonaktif tetap tersimpan beserta QR-nya, tetapi tidak dicetak pada
 * label default dan tidak boleh ditempati pallet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pallet_address')) {
            return;
        }

        if (! Schema::hasColumn('pallet_address', 'is_active')) {
            Schema::table('pallet_address', function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->after('country_mode');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pallet_address') && Schema::hasColumn('pallet_address', 'is_active')) {
            Schema::table('pallet_address', function (Blueprint $table) {
                $table->dropColumn('is_active');
            });
        }
    }
};