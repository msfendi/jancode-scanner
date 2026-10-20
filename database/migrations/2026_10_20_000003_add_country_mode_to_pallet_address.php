<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('pallet_address', 'country_mode')) {
            Schema::table('pallet_address', function (Blueprint $table) {
                // 'single' = pallet hanya boleh 1 negara (default, aturan lama)
                // 'multi'  = pallet boleh diisi beberapa negara
                $table->string('country_mode', 10)->default('single');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pallet_address', 'country_mode')) {
            Schema::table('pallet_address', function (Blueprint $table) {
                $table->dropColumn('country_mode');
            });
        }
    }
};
