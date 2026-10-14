<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pallet_assignment', function (Blueprint $table) {
            if (!Schema::hasColumn('pallet_assignment', 'ocf')) {
                $table->string('ocf')->nullable()->after('country');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pallet_assignment', function (Blueprint $table) {
            if (Schema::hasColumn('pallet_assignment', 'ocf')) {
                $table->dropColumn('ocf');
            }
        });
    }
};
