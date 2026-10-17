<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambahkan metadata PROFORMA / ACTUAL packing list ke tabel import_batches.
 *
 * Konsep:
 * - Satu baris import_batches = satu REVISI packing list (biasanya 1 sheet).
 * - Beberapa revisi dengan group_key yang sama membentuk 1 "Packing List".
 * - group_key = STYLE | OCF/CPO | DESTINATION | WAREHOUSE (huruf besar, trimmed).
 * - Revisi terbaru dari sebuah group ditandai is_active = true. Saat PACKING LIST
 *   ACTUAL diimport, ia otomatis menjadi revisi aktif (final).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            if (!Schema::hasColumn('import_batches', 'doc_type')) {
                $table->string('doc_type', 20)->default('proforma')->after('batch_name'); // proforma | actual
            }
            if (!Schema::hasColumn('import_batches', 'sheet_name')) {
                $table->string('sheet_name')->nullable()->after('original_filename');
            }
            if (!Schema::hasColumn('import_batches', 'style_number')) {
                $table->string('style_number')->nullable()->after('cpo');
            }
            if (!Schema::hasColumn('import_batches', 'destination')) {
                $table->string('destination')->nullable()->after('style_number');
            }
            if (!Schema::hasColumn('import_batches', 'warehouse')) {
                $table->string('warehouse')->nullable()->after('destination');
            }
            if (!Schema::hasColumn('import_batches', 'group_key')) {
                $table->string('group_key')->nullable()->after('warehouse');
            }
            if (!Schema::hasColumn('import_batches', 'group_label')) {
                $table->string('group_label')->nullable()->after('group_key');
            }
            if (!Schema::hasColumn('import_batches', 'revision_no')) {
                $table->integer('revision_no')->default(1)->after('group_label');
            }
            if (!Schema::hasColumn('import_batches', 'is_active')) {
                $table->boolean('is_active')->default(false)->after('revision_no');
            }
            if (!Schema::hasColumn('import_batches', 'total_carton')) {
                $table->integer('total_carton')->default(0)->after('is_active');
            }
            if (!Schema::hasColumn('import_batches', 'superseded_at')) {
                $table->timestamp('superseded_at')->nullable()->after('total_carton');
            }
        });

        Schema::table('import_batches', function (Blueprint $table) {
            $table->index('group_key');
            $table->index('is_active');
            $table->index(['group_key', 'revision_no']);
        });
    }

    public function down(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            $table->dropIndex(['group_key']);
            $table->dropIndex(['is_active']);
            $table->dropIndex(['group_key', 'revision_no']);
        });

        Schema::table('import_batches', function (Blueprint $table) {
            $cols = [
                'doc_type',
                'sheet_name',
                'style_number',
                'destination',
                'warehouse',
                'group_key',
                'group_label',
                'revision_no',
                'is_active',
                'total_carton',
                'superseded_at',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('import_batches', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
