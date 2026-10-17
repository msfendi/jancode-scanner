<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scan per PACKING LIST.
 *
 * - `import_batch_id` menunjuk revisi packing list (import_batches) yang di-scan.
 * - `jancode_id` dibuat nullable karena 1 kali submit = 1 batch scan untuk satu
 *   packing list (bukan lagi per single jancode).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scan_batches', function (Blueprint $table) {
            if (!Schema::hasColumn('scan_batches', 'import_batch_id')) {
                $table->unsignedBigInteger('import_batch_id')->nullable()->after('jancode_id');
                $table->index('import_batch_id');
            }
        });

        // 1 submit = 1 batch scan per packing list, sehingga jancode_id tidak wajib.
        if (Schema::hasColumn('scan_batches', 'jancode_id')) {
            Schema::table('scan_batches', function (Blueprint $table) {
                $table->unsignedBigInteger('jancode_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('scan_batches', 'import_batch_id')) {
            Schema::table('scan_batches', function (Blueprint $table) {
                $table->dropIndex(['import_batch_id']);
                $table->dropColumn('import_batch_id');
            });
        }
    }
};
