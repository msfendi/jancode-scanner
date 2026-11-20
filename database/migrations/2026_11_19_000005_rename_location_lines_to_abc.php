<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rename line lokasi pallet: dari 6 kolom (a–f) menjadi 3 line (a, b, c).
 *
 * Tiap line berisi 33 posisi menerus:
 *   depan  : 01–14
 *   belakang: 15–33
 *
 * Peta perubahan:
 *   a-01..14  -> a-01..14   (tetap)
 *   c-01..19  -> a-15..33
 *   b-01..14  -> b-01..14   (tetap)
 *   d-01..19  -> b-15..33
 *   e-01..14  -> c-01..14
 *   f-01..19  -> c-15..33
 *
 * Data penempatan (`pallet_assignment`, `pallet_assignment_detail`) terikat
 * lewat `pallet_address_id`, jadi tidak ikut berubah. Jumlah baris tetap 792.
 *
 * Urutan statement wajib bertahap karena `code` punya UNIQUE index dan
 * 152 kode baru bentrok dengan kode lama bila di-update sekaligus.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pallet_address')) {
            return;
        }

        // Sudah pernah dijalankan (tidak ada line d/e/f lagi) -> lewati.
        if (! DB::table('pallet_address')->whereIn('col_label', ['d', 'e', 'f'])->exists()) {
            return;
        }

        DB::transaction(function () {
            // Fase 1: bebaskan line lama c, d, f ke nama sementara.
            $this->renameLine('c', 'zc', 0);
            $this->renameLine('d', 'zd', 0);
            $this->renameLine('f', 'zf', 0);

            // Fase 2: susun nama final.
            $this->renameLine('e', 'c', 0);    // e-01..14  -> c-01..14
            $this->renameLine('zc', 'a', 14);  // c-01..19  -> a-15..33
            $this->renameLine('zd', 'b', 14);  // d-01..19  -> b-15..33
            $this->renameLine('zf', 'c', 14);  // f-01..19  -> c-15..33
        });

        $this->syncCartonOutPalletCode();
    }

    public function down(): void
    {
        if (! Schema::hasTable('pallet_address')) {
            return;
        }

        if (! DB::table('pallet_address')->whereIn('col_label', ['d', 'e', 'f'])->exists()) {
            DB::transaction(function () {
                // Fase 1: simpan sementara bagian belakang line a, b, dan c.
                $this->renameLineAbove('a', 'za', 15);
                $this->renameLineAbove('b', 'zb', 15);
                $this->renameLineAbove('c', 'zf', 15);

                // Fase 2: kembalikan ke nama lama.
                $this->renameLine('c', 'e', 0);    // c-01..14 -> e-01..14
                $this->renameLine('za', 'c', -14); // a-15..33 -> c-01..19
                $this->renameLine('zb', 'd', -14); // b-15..33 -> d-01..19
                $this->renameLine('zf', 'f', -14); // c-15..33 -> f-01..19
            });
        }

        $this->syncCartonOutPalletCode();
    }

    /**
     * Pindahkan seluruh baris pada satu line ke line tujuan dengan offset posisi.
     */
    private function renameLine(string $fromCol, string $toCol, int $positionOffset): void
    {
        $this->renameWhere('col_label', $fromCol, $toCol, $positionOffset, null);
    }

    /**
     * Pindahkan baris pada line tertentu yang posisinya >= batas (area belakang).
     */
    private function renameLineAbove(string $fromCol, string $toCol, int $minPosition): void
    {
        $this->renameWhere('col_label', $fromCol, $toCol, 0, $minPosition);
    }

    private function renameWhere(string $column, string $fromValue, string $toCol, int $positionOffset, ?int $minPosition): void
    {
        DB::table('pallet_address')
            ->where($column, $fromValue)
            ->when($minPosition !== null, fn ($q) => $q->where('position_no', '>=', $minPosition))
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($toCol, $positionOffset) {
                foreach ($rows as $row) {
                    $position = (int) $row->position_no + $positionOffset;

                    DB::table('pallet_address')->where('id', $row->id)->update([
                        'col_label'   => $toCol,
                        'position_no' => $position,
                        'code'        => sprintf('%s-%s-%02d', $row->row_label, $toCol, $position),
                    ]);
                }
            });
    }

    /**
     * Samakan teks `pallet_code` di carton_out_details dengan kode lokasi terbaru.
     *
     * Hanya baris yang punya relasi ke pallet_address dan kodenya sudah berbeda
     * yang disentuh (di produksi: 28 baris).
     */
    private function syncCartonOutPalletCode(): void
    {
        if (! Schema::hasTable('carton_out_details') || ! Schema::hasColumn('carton_out_details', 'pallet_code')) {
            return;
        }

        $rows = DB::table('carton_out_details as d')
            ->join('pallet_address as p', 'p.id', '=', 'd.pallet_address_id')
            ->whereNotNull('d.pallet_code')
            ->whereColumn('d.pallet_code', '<>', 'p.code')
            ->select('d.id', 'p.code as new_code')
            ->get();

        foreach ($rows as $row) {
            DB::table('carton_out_details')->where('id', $row->id)->update(['pallet_code' => $row->new_code]);
        }
    }
};