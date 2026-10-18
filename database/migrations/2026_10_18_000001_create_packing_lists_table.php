<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PACKING LIST sebagai entitas utama (stabil antar revisi).
 *
 * Latar belakang:
 * - Satu baris import_batches = satu REVISI (PROFORMA/ACTUAL).
 * - Beberapa revisi dengan group_key yang sama = SATU packing list.
 * - Barcode (jancode) yang sama bisa dipakai di packing list berbeda, jadi
 *   scan log & penempatan pallet harus terhubung ke ID PACKING LIST (bukan
 *   hanya ke baris jancode milik satu revisi).
 *
 * Tabel ini menyimpan identitas packing list yang tetap meski revisi berganti,
 * sehingga ketika revisi baru menjadi aktif seluruh scan dapat dibawa serta.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Tabel master packing list ─────────────────────────────────────
        if (!Schema::hasTable('packing_lists')) {
            Schema::create('packing_lists', function (Blueprint $table) {
                $table->id();
                $table->string('group_key')->unique();  // STYLE|OCF|DEST|WAREHOUSE
                $table->string('group_label')->nullable();
                $table->string('style_number')->nullable();
                $table->string('ocf_number')->nullable();
                $table->string('destination')->nullable();
                $table->string('warehouse')->nullable();
                $table->string('supplier_name')->nullable();
                $table->unsignedBigInteger('active_import_batch_id')->nullable();
                $table->timestamps();

                $table->index('active_import_batch_id');
            });
        }

        // ── 2. Kolom packing_list_id pada tabel terkait ──────────────────────
        Schema::table('import_batches', function (Blueprint $table) {
            if (!Schema::hasColumn('import_batches', 'packing_list_id')) {
                $table->unsignedBigInteger('packing_list_id')->nullable();
                $table->index('packing_list_id');
            }
        });

        Schema::table('jancode', function (Blueprint $table) {
            if (!Schema::hasColumn('jancode', 'packing_list_id')) {
                $table->unsignedBigInteger('packing_list_id')->nullable();
                $table->index('packing_list_id');
            }
        });

        Schema::table('jancode_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('jancode_logs', 'packing_list_id')) {
                $table->unsignedBigInteger('packing_list_id')->nullable();
                $table->index('packing_list_id');
            }
            if (!Schema::hasColumn('jancode_logs', 'import_batch_id')) {
                $table->unsignedBigInteger('import_batch_id')->nullable();
                $table->index('import_batch_id');
            }
        });

        Schema::table('scan_batches', function (Blueprint $table) {
            if (!Schema::hasColumn('scan_batches', 'packing_list_id')) {
                $table->unsignedBigInteger('packing_list_id')->nullable();
                $table->index('packing_list_id');
            }
        });

        Schema::table('pallet_assignment_detail', function (Blueprint $table) {
            if (!Schema::hasColumn('pallet_assignment_detail', 'packing_list_id')) {
                $table->unsignedBigInteger('packing_list_id')->nullable();
                $table->index('packing_list_id');
            }
        });

        // ── 3. Backfill data lama ────────────────────────────────────────────
        $this->backfill();

        // ── 4. Bersihkan kolom buangan ───────────────────────────────────────
    }

    private function backfill(): void
    {
        // 3a. Buat satu packing list per group_key di import_batches
        $groupKeys = DB::table('import_batches')
            ->whereNotNull('group_key')
            ->where('group_key', '!=', '')
            ->distinct()
            ->pluck('group_key');

        foreach ($groupKeys as $groupKey) {
            $batches = DB::table('import_batches')
                ->where('group_key', $groupKey)
                ->orderBy('revision_no')
                ->get();

            if ($batches->isEmpty()) {
                continue;
            }

            $latest = $batches->last();

            // Revisi aktif: ACTUAL terbaru menang, jika tidak ada ACTUAL maka
            // revisi terbaru.
            $active = $batches->firstWhere('doc_type', 'actual') ?: $latest;
            // Pastikan memakai ACTUAL terbaru bila ada lebih dari satu.
            $actives = $batches->where('doc_type', 'actual');
            if ($actives->isNotEmpty()) {
                $active = $actives->last();
            }

            $packingListId = DB::table('packing_lists')->insertGetId([
                'group_key'              => $groupKey,
                'group_label'            => $latest->group_label,
                'style_number'           => $latest->style_number,
                'ocf_number'             => $latest->ocf_number,
                'destination'            => $latest->destination,
                'warehouse'              => $latest->warehouse,
                'supplier_name'          => $latest->supplier_name,
                'active_import_batch_id' => $active->id,
                'created_at'             => now(),
                'updated_at'             => now(),
            ]);

            $batchIds = $batches->pluck('id')->all();

            DB::table('import_batches')
                ->whereIn('id', $batchIds)
                ->update(['packing_list_id' => $packingListId]);

            DB::table('jancode')
                ->whereIn('import_batch_id', $batchIds)
                ->update(['packing_list_id' => $packingListId]);

            DB::table('scan_batches')
                ->whereIn('import_batch_id', $batchIds)
                ->update(['packing_list_id' => $packingListId]);
        }

        // 3b. jancode_logs: turunkan packing list + revisi dari baris jancode
        if (Schema::hasTable('jancode_logs')) {
            DB::statement(
                'UPDATE jancode_logs
                 SET packing_list_id = (
                        SELECT j.packing_list_id FROM jancode j WHERE j.id = jancode_logs.jancode_id
                     ),
                     import_batch_id = (
                        SELECT j.import_batch_id FROM jancode j WHERE j.id = jancode_logs.jancode_id
                     )
                 WHERE jancode_id IS NOT NULL'
            );
        }

        // 3c. pallet_assignment_detail: turunkan packing list dari baris jancode
        if (Schema::hasTable('pallet_assignment_detail')) {
            DB::statement(
                'UPDATE pallet_assignment_detail
                 SET packing_list_id = (
                        SELECT j.packing_list_id FROM jancode j WHERE j.id = pallet_assignment_detail.jancode_id
                     )
                 WHERE jancode_id IS NOT NULL'
            );
        }
    }

    public function down(): void
    {
        Schema::table('pallet_assignment_detail', function (Blueprint $table) {
            if (Schema::hasColumn('pallet_assignment_detail', 'packing_list_id')) {
                $table->dropColumn('packing_list_id');
            }
        });

        Schema::table('scan_batches', function (Blueprint $table) {
            if (Schema::hasColumn('scan_batches', 'packing_list_id')) {
                $table->dropColumn('packing_list_id');
            }
        });

        Schema::table('jancode_logs', function (Blueprint $table) {
            if (Schema::hasColumn('jancode_logs', 'import_batch_id')) {
                $table->dropColumn('import_batch_id');
            }
            if (Schema::hasColumn('jancode_logs', 'packing_list_id')) {
                $table->dropColumn('packing_list_id');
            }
        });

        Schema::table('jancode', function (Blueprint $table) {
            if (Schema::hasColumn('jancode', 'packing_list_id')) {
                $table->dropColumn('packing_list_id');
            }
        });

        Schema::table('import_batches', function (Blueprint $table) {
            if (Schema::hasColumn('import_batches', 'packing_list_id')) {
                $table->dropColumn('packing_list_id');
            }
        });

        Schema::dropIfExists('packing_lists');
    }
};
