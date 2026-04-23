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
        Schema::create('jancode', function (Blueprint $table) {
            $table->id();
            $table->string('jancode');
            $table->string('size');
            $table->integer('qty');
            $table->string('country');
            $table->string('color');
            $table->string('description');
            $table->string('void')->default('false');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jancode');
    }
};
