<?php

namespace App\Services;

use App\Models\ImportBatch;
use App\Models\Jancode;
use App\Models\JancodeLogs;
use App\Models\PalletAssignmentDetail;
use App\Models\PackingList;
use Illuminate\Support\Facades\DB;

/**
 * Menjaga kesinambungan scan antar revisi dalam satu PACKING LIST.
 *
 * PACKING LIST adalah entitas utama yang tetap (stabil) meski revisi berganti.
 * Ketika sebuah revisi baru (PROFORMA rev berikutnya atau ACTUAL) menjadi aktif,
 * seluruh scan dan penempatan pallet yang sudah terjadi pada revisi sebelumnya
 * "dibawa" ke revisi aktif — dicocokkan per barcode — sehingga progres tidak
 * hilang. Setiap scan tetap terhubung ke packing_list_id-nya.
 */
class PackingListRevisionService
{
    /**
     * Dipanggil setelah sebuah revisi selesai diimport:
     * 1. Tentukan revisi aktif packing list.
     * 2. Sinkronkan pointer packing list.
     * 3. Bawa semua scan/pallet dari revisi lain ke revisi aktif.
     */
    public function syncActiveRevision(?string $groupKey, ?int $sessionId = null): ?ImportBatch
    {
        if (!$groupKey) {
            return null;
        }

        $packingList = PackingList::findOrCreateForGroupKey($groupKey, $sessionId);
        $active      = ImportBatch::recomputeActiveForGroup($groupKey, $sessionId);

        if ($packingList && $active) {
            $packingList->syncFromBatch($active, $active->id);
            $this->carryOverIntoActive($active);
        }

        return $active;
    }

    /**
     * Bawa seluruh scan log & detail pallet dari revisi lain (non-aktif) pada
     * packing list yang sama ke revisi aktif, dicocokkan per barcode. Lalu
     * hitung ulang receive_qty_carton & balance tiap baris revisi aktif.
     *
     * @return array{logs:int, pallets:int, unmapped:int}
     */
    public function carryOverIntoActive(ImportBatch $active): array
    {
        $stats = ['logs' => 0, 'pallets' => 0, 'unmapped' => 0];

        $otherBatchIds = ImportBatch::forGroup($active->group_key, $active->session_id)
            ->where('id', '!=', $active->id)
            ->pluck('id')
            ->all();

        if (empty($otherBatchIds)) {
            $this->recomputeBalances($active);
            return $stats;
        }

        $targetRows = Jancode::where('import_batch_id', $active->id)
            ->get(['id', 'jancode', 'color', 'size']);
        $byBarcode = $targetRows->groupBy('jancode');

        $oldRows = Jancode::whereIn('import_batch_id', $otherBatchIds)
            ->get(['id', 'jancode', 'color', 'size']);

        // Bangun peta perpindahan: targetId => [oldJancodeId, ...]
        $moves = [];
        foreach ($oldRows as $old) {
            $candidates = $byBarcode[$old->jancode] ?? null;

            if (!$candidates || $candidates->isEmpty()) {
                // Barcode tidak ada lagi di revisi aktif (baris dihapus).
                $stats['unmapped']++;
                continue;
            }

            $match = $candidates->firstWhere(
                fn ($c) => $c->color === $old->color && $c->size === $old->size
            ) ?: $candidates->first();

            $moves[$match->id][] = $old->id;
        }

        DB::transaction(function () use ($moves, $active, &$stats) {
            foreach ($moves as $targetId => $oldIds) {
                $stats['logs'] += JancodeLogs::whereIn('jancode_id', $oldIds)->update([
                    'jancode_id'      => $targetId,
                    'import_batch_id' => $active->id,
                    'packing_list_id' => $active->packing_list_id,
                    'session_id'      => $active->session_id,
                ]);

                $stats['pallets'] += PalletAssignmentDetail::whereIn('jancode_id', $oldIds)->update([
                    'jancode_id'      => $targetId,
                    'packing_list_id' => $active->packing_list_id,
                    'session_id'      => $active->session_id,
                ]);
            }
        });

        $this->recomputeBalances($active);

        return $stats;
    }

    /**
     * Hitung ulang receive_qty_carton & balance tiap baris revisi aktif
     * berdasarkan jumlah scan yang sudah tersubmit pada baris tsb.
     */
    public function recomputeBalances(ImportBatch $active): void
    {
        $rows = Jancode::where('import_batch_id', $active->id)->get();
        if ($rows->isEmpty()) {
            return;
        }

        $receivedMap = JancodeLogs::whereIn('jancode_id', $rows->pluck('id'))
            ->where('is_submitted', 'true')
            ->selectRaw('jancode_id, COUNT(*) as total')
            ->groupBy('jancode_id')
            ->pluck('total', 'jancode_id');

        foreach ($rows as $row) {
            $received = (int) ($receivedMap[$row->id] ?? 0);
            $packing  = (int) $row->packing_list_qty_carton;
            $balance  = max(0, $packing - $received);

            if ((int) $row->receive_qty_carton !== $received || (int) $row->balance !== $balance) {
                $row->update([
                    'receive_qty_carton' => $received,
                    'balance'            => $balance,
                ]);
            }
        }
    }
}
