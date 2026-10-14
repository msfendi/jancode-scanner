<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jancode', function (Blueprint $table) {
            if (!Schema::hasColumn('jancode', 'country')) {
                $table->string('country')->nullable()->after('id');
            }
            if (!Schema::hasColumn('jancode', 'buyer')) {
                $table->string('buyer')->nullable()->after('country');
            }
            if (!Schema::hasColumn('jancode', 'ocf')) {
                $table->string('ocf')->nullable()->after('buyer');
            }
            if (!Schema::hasColumn('jancode', 'style')) {
                $table->string('style')->nullable()->after('ocf');
            }
            if (!Schema::hasColumn('jancode', 'color')) {
                $table->string('color')->nullable()->after('style');
            }
            if (!Schema::hasColumn('jancode', 'packing_list_qty_carton')) {
                $table->integer('packing_list_qty_carton')->default(0)->after('size');
            }
            if (!Schema::hasColumn('jancode', 'receive_qty_carton')) {
                $table->integer('receive_qty_carton')->default(0)->after('packing_list_qty_carton');
            }
            if (!Schema::hasColumn('jancode', 'balance')) {
                $table->integer('balance')->default(0)->after('receive_qty_carton');
            }
        });
    }

    public function down(): void
    {
        Schema::table('jancode', function (Blueprint $table) {
            $table->dropColumn([
                'country',
                'buyer',
                'ocf',
                'style',
                'color',
                'packing_list_qty_carton',
                'receive_qty_carton',
                'balance',
            ]);
        });
    }
};
