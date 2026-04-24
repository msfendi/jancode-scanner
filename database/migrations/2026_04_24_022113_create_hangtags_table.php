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
        Schema::create('hangtags', function (Blueprint $table) {
            $table->id();
            $table->string('barcode');
            $table->string('color');
            $table->integer('qty');
            $table->string('country');
            $table->string('size');
            $table->string('buyer');
            $table->string('void')->default('false');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hangtags');
    }
};
