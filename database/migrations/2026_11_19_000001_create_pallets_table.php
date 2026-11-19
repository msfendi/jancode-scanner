<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Master pallet (objek fisik yang dipindah-pindah).
 *
 * Berbeda dari `pallet_address` yang merupakan lokasi tetap. Pada fase ini
 * pallet hanya berisi identitas + QR; relasi ke lokasi & isi karton belum
 * dibuat (fase penempatan terpisah).
 *
 * Kode memakai format PLT-0001. Seed awal 1000 baris; penambahan berikutnya
 * dilakukan admin dan melanjutkan nomor terakhir.
 */
return new class extends Migration
{
    /** Jumlah pallet yang di-seed otomatis. */
    private const SEED_COUNT = 1000;

    public function up(): void
    {
        if (! Schema::hasTable('pallets')) {
            Schema::create('pallets', function (Blueprint $table) {
                $table->id();
                $table->string('code', 12)->unique();
                $table->string('status', 12)->default('available');
                $table->integer('max_carton')->default(42);
                $table->string('zone', 20)->nullable();
                $table->string('notes', 255)->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('status');
            });
        }

        if (DB::table('pallets')->count() > 0) {
            return;
        }

        $now = now();
        $rows = [];

        for ($i = 1; $i <= self::SEED_COUNT; $i++) {
            $rows[] = [
                'code'       => sprintf('PLT-%04d', $i),
                'status'     => 'available',
                'max_carton' => 42,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('pallets')->insert($chunk);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pallets');
    }
};