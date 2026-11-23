<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rapikan `pallet_assignment` yang belum punya pallet_id.
 *
 * Terjadi bila ada penulisan lama (atau data yang dibuat sebelum ikatan
 * pallet↔lokasi ada) yang masih menempel langsung ke lokasi. Setiap baris
 * seperti itu diikatkan ke pallet yang berdiri di lokasinya.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pallet_assignment') || ! Schema::hasColumn('pallet_assignment', 'pallet_id')) {
            return;
        }

        $rows = DB::table('pallet_assignment as pa')
            ->join('pallets as p', 'p.current_location_id', '=', 'pa.pallet_address_id')
            ->whereNull('pa.pallet_id')
            ->select('pa.id', 'p.id as pallet_id')
            ->get();

        foreach ($rows as $row) {
            // Lewati bila pallet itu sudah dipakai baris lain (unique).
            $taken = DB::table('pallet_assignment')
                ->where('pallet_id', $row->pallet_id)
                ->where('id', '<>', $row->id)
                ->exists();

            if ($taken) {
                continue;
            }

            DB::table('pallet_assignment')->where('id', $row->id)->update([
                'pallet_id'  => $row->pallet_id,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        //
    }
};