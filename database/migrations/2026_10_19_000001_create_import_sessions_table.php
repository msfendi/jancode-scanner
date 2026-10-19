<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SESSION sebagai induk sebelum import packing list.
 *
 * Konsep:
 * - Satu session mengikat satu kombinasi STYLE | OCF | DESTINATION | WAREHOUSE.
 * - Di dalam session user bisa import beberapa file Excel (revisi PROFORMA/ACTUAL),
 *   manage proforma, scan, lalu pallet.
 * - Identitas packing list = session + group_key, sehingga dua file berbeda yang
 *   kebetulan header-nya sama tetap terpisah asalkan berada di session berbeda.
 * - Kode session: IMP-YYYYMMDD-HHMMSS-001 (increment global).
 *
 * Data lama dimasukkan ke satu session legacy agar tetap bisa dibuka.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Tabel session ─────────────────────────────────────────────────
        if (!Schema::hasTable('import_sessions')) {
            Schema::create('import_sessions', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();          // IMP-YYYYMMDD-HHMMSS-001
                $table->text('notes')->nullable();
                $table->string('group_key')->nullable();    // diisi saat import pertama
                $table->string('style_number')->nullable();
                $table->string('ocf_number')->nullable();
                $table->string('destination')->nullable();
                $table->string('warehouse')->nullable();
                $table->string('supplier_name')->nullable();
                $table->string('status', 20)->default('open'); // open | closed
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();

                $table->index('group_key');
                $table->index('user_id');
            });
        }

        // ── 2. Kolom session_id pada tabel terkait ───────────────────────────
        foreach (['import_batches', 'packing_lists', 'jancode_logs', 'scan_batches', 'pallet_assignment_detail'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'session_id')) {
                    $table->unsignedBigInteger('session_id')->nullable();
                    $table->index('session_id');
                }
            });
        }

        // ── 3. Backfill: satu session legacy untuk seluruh data lama ─────────
        $legacyId = DB::table('import_sessions')->where('code', 'like', 'IMP-%-001')->value('id');

        if (!$legacyId) {
            $earliest = DB::table('import_batches')->min('created_at');

            $legacyId = DB::table('import_sessions')->insertGetId([
                'code'       => 'IMP-' . now()->format('Ymd-His') . '-001',
                'notes'      => 'Data lama sebelum fitur Session (backfill otomatis).',
                'status'     => 'closed',
                'user_id'    => null,
                'started_at' => $earliest ?: now(),
                'closed_at'  => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach (['import_batches', 'packing_lists', 'jancode_logs', 'scan_batches', 'pallet_assignment_detail'] as $tableName) {
            DB::table($tableName)->whereNull('session_id')->update(['session_id' => $legacyId]);
        }

        // ── 4. Unique packing list kini per (session, group_key) ─────────────
        try {
            Schema::table('packing_lists', function (Blueprint $table) {
                $table->dropUnique('packing_lists_group_key_unique');
            });
        } catch (\Throwable $e) {
            // Index mungkin sudah tidak ada pada instalasi tertentu.
        }

        Schema::table('packing_lists', function (Blueprint $table) {
            $table->unique(['session_id', 'group_key'], 'packing_lists_session_group_unique');
        });
    }

    public function down(): void
    {
        try {
            Schema::table('packing_lists', function (Blueprint $table) {
                $table->dropUnique('packing_lists_session_group_unique');
                $table->unique('group_key');
            });
        } catch (\Throwable $e) {
            // abaikan
        }

        foreach (['import_batches', 'packing_lists', 'jancode_logs', 'scan_batches', 'pallet_assignment_detail'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'session_id')) {
                    $table->dropColumn('session_id');
                }
            });
        }

        Schema::dropIfExists('import_sessions');
    }
};
