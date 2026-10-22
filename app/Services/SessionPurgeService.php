<?php

namespace App\Services;

use App\Models\CartonOut;
use App\Models\CartonOutDetail;
use App\Models\ImportBatch;
use App\Models\ImportFailedRow;
use App\Models\ImportSession;
use App\Models\Jancode;
use App\Models\JancodeLogs;
use App\Models\PackingList;
use App\Models\PalletAssignment;
use App\Models\PalletAssignmentDetail;
use App\Models\ScanBatch;
use Illuminate\Support\Facades\DB;

/**
 * Hapus permanen (hard delete) satu session beserta seluruh datanya:
 * revisi import, packing list, jancode, log scan, batch scan, penempatan
 * pallet, dan karton keluar.
 *
 * Hanya untuk Super Admin (permission `session.hard-delete`).
 */
class SessionPurgeService
{
    /**
     * @return array<string, int> jumlah data yang dihapus
     */
    public function purge(ImportSession $session): array
    {
        return DB::transaction(function () use ($session) {
            $sessionId = (int) $session->id;

            $batchIds = ImportBatch::where('session_id', $sessionId)->pluck('id')->all();
            $listIds  = PackingList::where('session_id', $sessionId)->pluck('id')->all();

            $jancodeIds = Jancode::where('session_id', $sessionId)
                ->when($batchIds, fn ($q) => $q->orWhereIn('import_batch_id', $batchIds))
                ->when($listIds, fn ($q) => $q->orWhereIn('packing_list_id', $listIds))
                ->pluck('id')
                ->all();

            $counts = [
                'carton_out_details' => 0,
                'carton_outs'        => 0,
                'pallet_details'     => 0,
                'pallet_assignments' => 0,
                'scan_batches'       => 0,
                'jancode_logs'       => 0,
                'jancodes'           => 0,
                'failed_rows'        => 0,
                'batches'            => 0,
                'packing_lists'      => 0,
                'sessions'           => 0,
            ];

            // ── 1. Karton keluar (detail + header) ──────────────────────
            $cartonOutIds = CartonOut::where('session_id', $sessionId)->pluck('id')->all();

            if (! empty($jancodeIds)) {
                $cartonOutIds = array_values(array_unique(array_merge(
                    $cartonOutIds,
                    CartonOutDetail::whereIn('jancode_id', $jancodeIds)->pluck('carton_out_id')->all()
                )));
            }

            if (! empty($cartonOutIds)) {
                $counts['carton_out_details'] = CartonOutDetail::whereIn('carton_out_id', $cartonOutIds)->delete();
                $counts['carton_outs']        = CartonOut::whereIn('id', $cartonOutIds)->delete();
            }

            // ── 2. Penempatan pallet ─────────────────────────────────────
            $detailQuery = PalletAssignmentDetail::where('session_id', $sessionId);

            if (! empty($jancodeIds)) {
                $detailQuery->orWhereIn('jancode_id', $jancodeIds);
            }
            if (! empty($listIds)) {
                $detailQuery->orWhereIn('packing_list_id', $listIds);
            }

            $assignmentIds = (clone $detailQuery)->pluck('assignment_id')->unique()->all();
            $counts['pallet_details'] = (clone $detailQuery)->delete();

            foreach ($assignmentIds as $assignmentId) {
                $assignment = PalletAssignment::lockForUpdate()->find($assignmentId);

                if (! $assignment) {
                    continue;
                }

                $total = (int) PalletAssignmentDetail::where('assignment_id', $assignmentId)->sum('qty_carton');

                if ($total <= 0) {
                    $assignment->delete();
                    $counts['pallet_assignments']++;
                } else {
                    $assignment->update(['total_carton' => $total]);
                }
            }

            // ── 3. Log scan + batch scan ────────────────────────────────
            $logQuery = JancodeLogs::where('session_id', $sessionId);

            if (! empty($jancodeIds)) {
                $logQuery->orWhereIn('jancode_id', $jancodeIds);
            }
            if (! empty($listIds)) {
                $logQuery->orWhereIn('packing_list_id', $listIds);
            }
            if (! empty($batchIds)) {
                $logQuery->orWhereIn('import_batch_id', $batchIds);
            }

            $counts['jancode_logs'] = (clone $logQuery)->delete();

            $scanQuery = ScanBatch::where('session_id', $sessionId);

            if (! empty($jancodeIds)) {
                $scanQuery->orWhereIn('jancode_id', $jancodeIds);
            }
            if (! empty($batchIds)) {
                $scanQuery->orWhereIn('import_batch_id', $batchIds);
            }
            if (! empty($listIds)) {
                $scanQuery->orWhereIn('packing_list_id', $listIds);
            }

            $counts['scan_batches'] = (clone $scanQuery)->delete();

            // ── 4. Jancode master ────────────────────────────────────────
            $jancodeQuery = Jancode::where('session_id', $sessionId);

            if (! empty($batchIds)) {
                $jancodeQuery->orWhereIn('import_batch_id', $batchIds);
            }
            if (! empty($listIds)) {
                $jancodeQuery->orWhereIn('packing_list_id', $listIds);
            }

            $counts['jancodes'] = (clone $jancodeQuery)->delete();

            // ── 5. Baris failed + revisi import ──────────────────────────
            if (! empty($batchIds)) {
                $counts['failed_rows'] = ImportFailedRow::whereIn('import_batch_id', $batchIds)->delete();
                $counts['batches']    = ImportBatch::whereIn('id', $batchIds)->delete();
            }

            // ── 6. Packing list + session ────────────────────────────────
            $counts['packing_lists'] = PackingList::where('session_id', $sessionId)->delete();
            $counts['sessions']     = (int) $session->delete();

            return $counts;
        });
    }

    /**
     * Ringkasan data yang akan terhapus (untuk konfirmasi di UI).
     *
     * @return array<string, int>
     */
    public function preview(ImportSession $session): array
    {
        $sessionId = (int) $session->id;

        $batchIds = ImportBatch::where('session_id', $sessionId)->pluck('id');
        $listIds  = PackingList::where('session_id', $sessionId)->pluck('id');

        $jancodes = Jancode::where('session_id', $sessionId)
            ->when($batchIds->isNotEmpty(), fn ($q) => $q->orWhereIn('import_batch_id', $batchIds))
            ->when($listIds->isNotEmpty(), fn ($q) => $q->orWhereIn('packing_list_id', $listIds))
            ->pluck('id');

        return [
            'packing_lists' => $listIds->count(),
            'batches'       => $batchIds->count(),
            'jancodes'      => $jancodes->count(),
            'jancode_logs'  => $jancodes->isEmpty()
                ? JancodeLogs::where('session_id', $sessionId)->count()
                : JancodeLogs::whereIn('jancode_id', $jancodes)->count(),
            'scan_batches'  => $batchIds->isEmpty()
                ? ScanBatch::where('session_id', $sessionId)->count()
                : ScanBatch::whereIn('import_batch_id', $batchIds)->count(),
        ];
    }
}
