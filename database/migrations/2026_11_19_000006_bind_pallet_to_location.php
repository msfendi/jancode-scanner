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

        $this->backfill();

        // Lokasi yang sudah terisi wajib punya pallet setelah backfill.
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
     * Beri identitas pallet untuk setiap lokasi yang sudah berisi karton.
     *
     * Pallet diambil dari stok `available` dengan kode terkecil, berurutan
     * mengikuti lokasi (row, line, pos). Idempoten: baris yang sudah punya
     * pallet dilewati.
     */
    private function backfill(): void
    {
        if (! Schema::hasTable('pallet_assignment') || ! Schema::hasColumn('pallet_assignment', 'pallet_id')) {
            return;
        }

        $assignments = DB::table('pallet_assignment as pa')
            ->join('pallet_address as a', 'a.id', '=', 'pa.pallet_address_id')
            ->whereNull('pa.pallet_id')
            ->orderBy('a.row_label')
            ->orderBy('a.col_label')
            ->orderBy('a.position_no')
            ->select('pa.id', 'pa.pallet_address_id')
            ->get();

        if ($assignments->isEmpty()) {
            return;
        }

        $freePallets = DB::table('pallets')
            ->whereNull('current_location_id')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $now = now();

        foreach ($assignments as $index => $assignment) {
            $palletId = $freePallets[$index] ?? null;

            if (! $palletId) {
                break; // stok pallet habis; sisanya dibiarkan terbuka
            }

            DB::table('pallets')->where('id', $palletId)->update([
                'current_location_id' => $assignment->pallet_address_id,
                'placed_at'           => $now,
                'status'              => 'in_use',
                'updated_at'          => $now,
            ]);

            DB::table('pallet_assignment')->where('id', $assignment->id)->update([
                'pallet_id'  => $palletId,
                'updated_at' => $now,
            ]);

            DB::table('pallet_placements')->insert([
                'pallet_id'   => $palletId,
                'location_id' => $assignment->pallet_address_id,
                'placed_at'   => $now,
                'removed_at'  => null,
                'placed_by'   => null,
                'removed_by'  => null,
                'reason'      => 'placed',
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }
    }

    /**
     * Samakan denormalisasi pallet_address_id pada pallet_assignment dengan
     * lokasi pallet saat ini.
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