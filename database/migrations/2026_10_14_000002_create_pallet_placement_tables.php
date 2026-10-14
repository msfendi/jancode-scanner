<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambah qty_per_carton dan total_qty_pcs ke jancode jika belum ada
        Schema::table('jancode', function (Blueprint $table) {
            if (!Schema::hasColumn('jancode', 'qty_per_carton')) {
                $table->integer('qty_per_carton')->default(0)->after('size');
            }
            if (!Schema::hasColumn('jancode', 'total_qty_pcs')) {
                $table->integer('total_qty_pcs')->default(0)->after('balance');
            }
        });

        // 2. Tabel Master Alamat Pallet (480 posisi)
        if (!Schema::hasTable('pallet_address')) {
            Schema::create('pallet_address', function (Blueprint $table) {
                $table->id();
                $table->string('row_label', 5);    // B sampai I
                $table->string('col_label', 5);    // a, b, c, d
                $table->integer('position_no');     // a/b: 1-14; c/d: 1-16
                $table->string('code', 20)->unique(); // B-a-01 s/d I-d-16
                $table->integer('max_carton')->default(42);
                $table->timestamps();

                $table->index(['row_label', 'col_label', 'position_no']);
            });
        }

        // 3. Tabel Pallet Assignment (Alamat pallet yang aktif terisi)
        if (!Schema::hasTable('pallet_assignment')) {
            Schema::create('pallet_assignment', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('pallet_address_id')->unique();
                $table->string('style');
                $table->string('country');
                $table->integer('total_carton')->default(0);
                $table->timestamps();

                $table->foreign('pallet_address_id')->references('id')->on('pallet_address')->onDelete('cascade');
            });
        }

        // 4. Tabel Detail Penempatan Karton Item di Pallet
        if (!Schema::hasTable('pallet_assignment_detail')) {
            Schema::create('pallet_assignment_detail', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('assignment_id');
                $table->unsignedBigInteger('jancode_id');
                $table->integer('qty_carton')->default(0);
                $table->timestamps();

                $table->foreign('assignment_id')->references('id')->on('pallet_assignment')->onDelete('cascade');
                $table->foreign('jancode_id')->references('id')->on('jancode')->onDelete('cascade');
            });
        }

        // 5. Seed otomatis 480 Alamat Pallet (BR-07) jika masih kosong
        if (DB::table('pallet_address')->count() === 0) {
            $rows = ['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'];
            $now = now();
            $addresses = [];

            foreach ($rows as $row) {
                // Kolom a (1-14)
                for ($p = 1; $p <= 14; $p++) {
                    $code = sprintf('%s-a-%02d', $row, $p);
                    $addresses[] = [
                        'row_label'   => $row,
                        'col_label'   => 'a',
                        'position_no' => $p,
                        'code'        => $code,
                        'max_carton'  => 42,
                        'created_at'  => $now,
                        'updated_at'  => $now,
                    ];
                }

                // Kolom b (1-14)
                for ($p = 1; $p <= 14; $p++) {
                    $code = sprintf('%s-b-%02d', $row, $p);
                    $addresses[] = [
                        'row_label'   => $row,
                        'col_label'   => 'b',
                        'position_no' => $p,
                        'code'        => $code,
                        'max_carton'  => 42,
                        'created_at'  => $now,
                        'updated_at'  => $now,
                    ];
                }

                // Kolom c (1-16)
                for ($p = 1; $p <= 16; $p++) {
                    $code = sprintf('%s-c-%02d', $row, $p);
                    $addresses[] = [
                        'row_label'   => $row,
                        'col_label'   => 'c',
                        'position_no' => $p,
                        'code'        => $code,
                        'max_carton'  => 42,
                        'created_at'  => $now,
                        'updated_at'  => $now,
                    ];
                }

                // Kolom d (1-16)
                for ($p = 1; $p <= 16; $p++) {
                    $code = sprintf('%s-d-%02d', $row, $p);
                    $addresses[] = [
                        'row_label'   => $row,
                        'col_label'   => 'd',
                        'position_no' => $p,
                        'code'        => $code,
                        'max_carton'  => 42,
                        'created_at'  => $now,
                        'updated_at'  => $now,
                    ];
                }
            }

            foreach (array_chunk($addresses, 100) as $chunk) {
                DB::table('pallet_address')->insert($chunk);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pallet_assignment_detail');
        Schema::dropIfExists('pallet_assignment');
        Schema::dropIfExists('pallet_address');

        Schema::table('jancode', function (Blueprint $table) {
            if (Schema::hasColumn('jancode', 'qty_per_carton')) {
                $table->dropColumn('qty_per_carton');
            }
            if (Schema::hasColumn('jancode', 'total_qty_pcs')) {
                $table->dropColumn('total_qty_pcs');
            }
        });
    }
};
