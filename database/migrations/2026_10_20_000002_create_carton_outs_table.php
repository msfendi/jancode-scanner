<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat KARTON KELUAR (scan out).
 *
 * carton_outs        : header 1 batch pengambilan karton (BOUT-YYYYMMDD-0001).
 * carton_out_details : rincian per baris — color, size, jancode, qty, dan
 *                      lokasi pallet asal (jika karton sudah ditempatkan).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('carton_outs')) {
            Schema::create('carton_outs', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();                   // BOUT-YYYYMMDD-0001
                $table->unsignedBigInteger('session_id')->nullable()->index();
                $table->unsignedBigInteger('packing_list_id')->nullable()->index();
                $table->unsignedBigInteger('import_batch_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->integer('total_carton')->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('carton_out_details')) {
            Schema::create('carton_out_details', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('carton_out_id')->index();
                $table->unsignedBigInteger('jancode_id')->index();
                $table->string('jancode')->nullable();
                $table->string('color')->nullable();
                $table->string('size')->nullable();
                $table->integer('qty_carton')->default(0);
                $table->unsignedBigInteger('pallet_address_id')->nullable()->index();
                $table->string('pallet_code')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('carton_out_details');
        Schema::dropIfExists('carton_outs');
    }
};
