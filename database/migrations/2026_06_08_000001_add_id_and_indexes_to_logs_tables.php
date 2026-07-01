<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── hangtag_logs ────────────────────────────────────────────────────
        Schema::table('hangtag_logs', function (Blueprint $table) {
            // Add auto-increment primary key so Eloquent delete() works correctly
            $table->id()->first();

            // Composite indexes for the COUNT(*) queries fired on every scan
            $table->index(['barcode', 'is_submitted'], 'ht_logs_barcode_submitted');
            $table->index(['barcode', 'user_id', 'is_submitted'], 'ht_logs_barcode_user_submitted');
        });

        // ── jancode_logs ─────────────────────────────────────────────────────
        Schema::table('jancode_logs', function (Blueprint $table) {
            $table->id()->first();

            $table->index(['jancode', 'is_submitted'], 'jc_logs_jancode_submitted');
            $table->index(['jancode', 'user_id', 'is_submitted'], 'jc_logs_jancode_user_submitted');
        });
    }

    public function down(): void
    {
        Schema::table('hangtag_logs', function (Blueprint $table) {
            $table->dropIndex('ht_logs_barcode_submitted');
            $table->dropIndex('ht_logs_barcode_user_submitted');
            $table->dropColumn('id');
        });

        Schema::table('jancode_logs', function (Blueprint $table) {
            $table->dropIndex('jc_logs_jancode_submitted');
            $table->dropIndex('jc_logs_jancode_user_submitted');
            $table->dropColumn('id');
        });
    }
};
