<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite tidak mendukung ALTER TABLE ... ADD PRIMARY KEY, sehingga tabel
        // dibangun ulang dengan kolom id sebagai primary key. Baris lama (jika ada)
        // tetap dipindahkan.
        if (DB::connection()->getDriverName() === 'sqlite') {
            // Catatan: kolom jancode_id ditambahkan oleh migrasi berikutnya
            // (2026_07_01_080831), jadi tidak dimasukkan di sini.
            $this->rebuildLogsTableForSqlite('jancode_logs', ['jancode', 'user_id', 'is_submitted'], 'jc');
            $this->rebuildLogsTableForSqlite('hangtag_logs', ['barcode', 'user_id', 'is_submitted'], 'ht');

            return;
        }

        // ── hangtag_logs ────────────────────────────────────────────────────
        Schema::table('hangtag_logs', function (Blueprint $table) {
            // Add auto-increment primary key so Eloquent delete() works correctly
            $table->id()->first();

            // Composite indexes for the COUNT(*) queries fired on every scan
            $table->index(['barcode', 'is_submitted'], 'ht_logs_barcode_submitted');
            $table->index(['barcode', 'user_id', 'is_submitted'], 'ht_logs_barcode_user_submitted');
        });

        // ── jancode_logs ─────────────────────────────────────────────────────
        Schema::table('jancode_logs', function (Blueprint $table) {
            $table->id()->first();

            $table->index(['jancode', 'is_submitted'], 'jc_logs_jancode_submitted');
            $table->index(['jancode', 'user_id', 'is_submitted'], 'jc_logs_jancode_user_submitted');
        });
    }

    private function rebuildLogsTableForSqlite(string $table, array $columns, string $prefix): void
    {
        $rows = Schema::hasTable($table) ? DB::table($table)->get() : collect();

        Schema::dropIfExists($table);

        Schema::create($table, function (Blueprint $blueprint) use ($columns, $prefix) {
            $blueprint->id();

            foreach ($columns as $column) {
                if ($column === 'user_id' || str_ends_with($column, '_id')) {
                    $blueprint->unsignedBigInteger($column)->nullable();
                } else {
                    $blueprint->string($column);
                }
            }

            $blueprint->timestamps();

            foreach ($columns as $column) {
                if (str_ends_with($column, '_id')) {
                    $blueprint->index($column);
                }
            }

            $mainKey = $columns[0];
            $blueprint->index([$mainKey, 'is_submitted'], "{$prefix}_logs_{$mainKey}_submitted");
            $blueprint->index([$mainKey, 'user_id', 'is_submitted'], "{$prefix}_logs_{$mainKey}_user_submitted");
        });

        foreach ($rows as $row) {
            $data = (array) $row;
            unset($data['id']); // id auto-increment diisi ulang
            DB::table($table)->insert($data);
        }
    }

    public function down(): void
    {
        foreach (['hangtag_logs', 'jancode_logs'] as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('id');
            });
        }
    }
};
