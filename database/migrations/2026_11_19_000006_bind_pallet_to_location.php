<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ikat pallet ke lokasi, dan isi karton ke pallet.
 *
 * Sebelumnya: karton (pallet_assignment) menempel langsung ke lokasi.
 * Sesudahnya : karton → pallet_assignment → pallet → lokasi.
 *
 * Aturan yang dijaga di level DB:
 *   - pallets.current_location_id UNIQUE  -> 1 lokasi maksimal 1 pallet
 *   - pallet_assignment.pallet_id UNIQUE  -> 1 pallet punya 1 wadah isi
 *
 * `pallet_assignment.pallet_address_id` tetap dipertahankan sebagai
 * denormalisasi (nilai turunan dari pallets.current_location_id) supaya board
 * dan view lama tidak perlu dirombak sekaligus.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pallets') || ! Schema::hasTable('pallet_address')) {
            return;
        }

        if (! Schema::hasColumn('pallets', 'current_location_id')) {
            Schema::table('pallets', function (Blueprint $table) {
                $table->unsignedBigInteger('current_location_id')->nullable()->after('max_carton');
                $table->timestamp('placed_at')->nullable()->after('current_location_id');
                $table->unique('current_location_id', 'pallets_current_location_unique');
            });
        }

        if (Schema::hasTable('pallet_assignment') && ! Schema::hasColumn('pallet_assignment', 'pallet_id')) {
            Schema::table('pallet_assignment', function (Blueprint $table) {
                $table->unsignedBigInteger('pallet_id')->nullable()->after('pallet_address_id');
                $table->unique('pallet_id', 'pallet_assignment_pallet_unique');
            });
        }

        if (! Schema::hasTable('pallet_placements')) {
            Schema::create('pallet_placements', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('pallet_id');
                $table->unsignedBigInteger('location_id');
                $table->timestamp('placed_at')->nullable();
                $table->timestamp('removed_at')->nullable();
                $table->unsignedBigInteger('placed_by')->nullable();
                $table->unsignedBigInteger('removed_by')->nullable();
                $table->string('reason', 20)->default('placed');
                $table->timestamps();

                $table->index(['pallet_id', 'removed_at'], 'pp_pallet_active');
                $table->index(['location_id', 'removed_at'], 'pp_location_active');
            });
        }

        $this->syncAssignmentLocationIds();
    }

    public function down(): void
    {
        if (Schema::hasTable('pallet_placements')) {
            Schema::dropIfExists('pallet_placements');
        }

        if (Schema::hasColumn('pallet_assignment', 'pallet_id')) {
            Schema::table('pallet_assignment', function (Blueprint $table) {
                $table->dropUnique('pallet_assignment_pallet_unique');
                $table->dropColumn('pallet_id');
            });
        }

        if (Schema::hasColumn('pallets', 'current_location_id')) {
            Schema::table('pallets', function (Blueprint $table) {
                $table->dropUnique('pallets_current_location_unique');
                $table->dropColumn(['current_location_id', 'placed_at']);
            });
        }
    }

    /**
     * Samakan denormalisasi pallet_address_id pada pallet_assignment dengan
     * lokasi pallet saat ini.
     *
     * CATATAN: tidak ada backfill otomatis. Data lama yang masih menempel ke
     * lokasi TIDAK otomatis diberi pallet — penempatan pallet wajib lewat
     * scan QR (lokasi + pallet) supaya identitas pallet benar-benarphysical.
     */
    private function syncAssignmentLocationIds(): void
    {
        if (! Schema::hasColumn('pallet_assignment', 'pallet_id')) {
            return;
        }

        DB::statement('UPDATE pallet_assignment SET pallet_address_id = (
            SELECT p.current_location_id FROM pallets p WHERE p.id = pallet_assignment.pallet_id
        ) WHERE pallet_id IS NOT NULL');
    }
};