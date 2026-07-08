<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('jancode_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('jancode_id')->nullable()->after('id');
            $table->index('jancode_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jancode_logs', function (Blueprint $table) {
            $table->dropIndex(['jancode_id']);
            $table->dropColumn('jancode_id');
        });
    }
};
