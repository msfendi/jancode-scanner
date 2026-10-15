<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jancode', function (Blueprint $table) {
            if (!Schema::hasColumn('jancode', 'carton_no_from')) {
                $table->integer('carton_no_from')->nullable()->after('style');
            }
            if (!Schema::hasColumn('jancode', 'carton_no_to')) {
                $table->integer('carton_no_to')->nullable()->after('carton_no_from');
            }
            if (!Schema::hasColumn('jancode', 'nw_per_carton')) {
                $table->decimal('nw_per_carton', 10, 3)->nullable()->after('total_qty_pcs');
            }
            if (!Schema::hasColumn('jancode', 'ttl_nw')) {
                $table->decimal('ttl_nw', 12, 3)->nullable()->after('nw_per_carton');
            }
            if (!Schema::hasColumn('jancode', 'gw_per_carton')) {
                $table->decimal('gw_per_carton', 10, 3)->nullable()->after('ttl_nw');
            }
            if (!Schema::hasColumn('jancode', 'ttl_gw')) {
                $table->decimal('ttl_gw', 12, 3)->nullable()->after('gw_per_carton');
            }
            if (!Schema::hasColumn('jancode', 'measurement_cm')) {
                $table->string('measurement_cm')->nullable()->after('ttl_gw');
            }
            if (!Schema::hasColumn('jancode', 'cbm')) {
                $table->decimal('cbm', 12, 6)->nullable()->after('measurement_cm');
            }
            if (!Schema::hasColumn('jancode', 'source_row')) {
                $table->integer('source_row')->nullable()->after('import_batch_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('jancode', function (Blueprint $table) {
            $cols = [
                'carton_no_from',
                'carton_no_to',
                'nw_per_carton',
                'ttl_nw',
                'gw_per_carton',
                'ttl_gw',
                'measurement_cm',
                'cbm',
                'source_row',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('jancode', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
