<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jancode', function (Blueprint $table) {
            if (!Schema::hasColumn('jancode', 'destination')) {
                $table->string('destination')->nullable()->after('country');
            }
            if (!Schema::hasColumn('jancode', 'cpo')) {
                $table->string('cpo')->nullable()->after('ocf');
            }
        });
    }

    public function down(): void
    {
        Schema::table('jancode', function (Blueprint $table) {
            foreach (['destination', 'cpo'] as $col) {
                if (Schema::hasColumn('jancode', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
