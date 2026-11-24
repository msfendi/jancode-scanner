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
 *   - pallets.current_location_id  unique -> 1 lokasi maksimal 1 pallet
 *   - pallet_assignment.pallet_id  unique -> 1 pallet punya 1 wadah isi
 *
 * PENTING (SQL Server): NULL dianggap sebagai nilai pada UNIQUE index, jadi
 * index biasa hanya mengizinkan SATU baris NULL. Karena ribuan baris memang
 * bernilai NULL (pallet belum ditempatkan / karton pada data lama), index
 * harus dibuat sebagai FILTERED unique index (`WHERE kolom IS NOT NULL`).
 * SQLite & MySQL mengizinkan banyak NULL pada unique index, jadi cukup index
 * biasa di sana.
 *
 * `pallet_assignment.pallet_address_id` tetap dipertahankan sebagai
 * denormalisasi (nilai turunan dari pallets.current_location_id) supaya board
 * dan view lama tidak perlu dirombak sekaligus.
 */
return new class extends Migration
{
    private const IDX_PALLET_LOCATION = 'pallets_current_location_unique';
    private const IDX_ASSIGNMENT_PALLET = 'pallet_assignment_pallet_unique';

    public function up(): void
    {
        if (! Schema::hasTable('pallets') || ! Schema::hasTable('pallet_address')) {
            return;
        }

        if (! Schema::hasColumn('pallets', 'current_location_id')) {
            Schema::table('pallets', function (Blueprint $table) {
                $table->unsignedBigInteger('current_location_id')->nullable()->after('max_carton');
                $table->timestamp('placed_at')->nullable()->after('current_location_id');
            });
        }

        $this->uniqueIgnoringNull('pallets', 'current_location_id', self::IDX_PALLET_LOCATION);

        if (Schema::hasTable('pallet_assignment') && ! Schema::hasColumn('pallet_assignment', 'pallet_id')) {
            Schema::table('pallet_assignment', function (Blueprint $table) {
                $table->unsignedBigInteger('pallet_id')->nullable()->after('pallet_address_id');
            });
        }

        if (Schema::hasColumn('pallet_assignment', 'pallet_id')) {
            $this->uniqueIgnoringNull('pallet_assignment', 'pallet_id', self::IDX_ASSIGNMENT_PALLET);
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
            $this->dropUniqueIgnoringNull('pallet_assignment', 'pallet_id', self::IDX_ASSIGNMENT_PALLET);

            Schema::table('pallet_assignment', function (Blueprint $table) {
                $table->dropColumn('pallet_id');
            });
        }

        if (Schema::hasColumn('pallets', 'current_location_id')) {
            $this->dropUniqueIgnoringNull('pallets', 'current_location_id', self::IDX_PALLET_LOCATION);

            Schema::table('pallets', function (Blueprint $table) {
                $table->dropColumn(['current_location_id', 'placed_at']);
            });
        }
    }

    /**
     * Buat unique index yang MENGABAIKAN baris NULL.
     *
     * SQL Server        : filtered unique index (WHERE kolom IS NOT NULL)
     * SQLite / MySQL    : unique index biasa (NULL boleh berulang)
     */
    private function uniqueIgnoringNull(string $table, string $column, string $name): void
    {
        if ($this->indexExists($table, $name)) {
            return;
        }

        if (DB::connection()->getDriverName() === 'sqlsrv') {
            DB::statement(sprintf(
                'CREATE UNIQUE INDEX %s ON %s (%s) WHERE %s IS NOT NULL',
                $name,
                $table,
                $column,
                $column
            ));

            return;
        }

        Schema::table($table, function (Blueprint $t) use ($column, $name) {
            $t->unique($column, $name);
        });
    }

    /**
     * Hapus unique index yang dibuat `uniqueIgnoringNull()`.
     */
    private function dropUniqueIgnoringNull(string $table, string $column, string $name): void
    {
        if (! $this->indexExists($table, $name)) {
            return;
        }

        if (DB::connection()->getDriverName() === 'sqlsrv') {
            DB::statement(sprintf('DROP INDEX %s ON %s', $name, $table));

            return;
        }

        Schema::table($table, function (Blueprint $t) use ($name) {
            $t->dropUnique($name);
        });
    }

    private function indexExists(string $table, string $name): bool
    {
        try {
            if (DB::connection()->getDriverName() === 'sqlsrv') {
                $row = DB::selectOne(
                    "SELECT COUNT(*) AS n FROM sys.indexes WHERE name = ? AND object_id = OBJECT_ID(?)",
                    [$name, $table]
                );

                return (int) ($row->n ?? 0) > 0;
            }

            $rows = DB::select("PRAGMA index_list('{$table}')");

            foreach ($rows as $row) {
                if (($row->name ?? null) === $name) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            return false;
        }

        return false;
    }

    /**
     * Samakan denormalisasi pallet_address_id pada pallet_assignment dengan
     * lokasi pallet saat ini.
     *
     * CATATAN: tidak ada backfill otomatis. Data lama yang masih menempel ke
     * lokasi TIDAK otomatis diberi pallet — penempatan pallet wajib lewat
     * scan QR (lokasi + pallet) supaya identitas pallet benar-benar sesuai fisik.
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