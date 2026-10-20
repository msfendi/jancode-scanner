<?php

namespace App\Services;

use App\Models\ImportBatch;
use App\Models\Jancode;
use App\Models\PackingList;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Kelengkapan packing list + snapshot report FINISHED.
 *
 * Report dibuat dari snapshot saat finish supaya tetap menunjukkan semua
 * balance = 0, walaupun setelah itu ada carton out yang mengubah balance
 * master jancode.
 */
class PackingListReportService
{
    public function activeBatch(PackingList $packingList): ?ImportBatch
    {
        return ImportBatch::where('packing_list_id', $packingList->id)
            ->where('session_id', $packingList->session_id)
            ->active()
            ->orderByDesc('revision_no')
            ->orderByDesc('id')
            ->first()
            ?: $packingList->activeImportBatch;
    }

    /**
     * Semua baris jancode pada revisi aktif (tanpa baris void).
     */
    public function reportRows(ImportBatch $batch): Collection
    {
        return Jancode::where('import_batch_id', $batch->id)
            ->where(function ($query) {
                $query->where('void', 'false')->orWhereNull('void');
            })
            ->orderByRaw('CASE WHEN carton_no_from IS NULL THEN 1 ELSE 0 END, carton_no_from ASC')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array{batch:?ImportBatch,rows:Collection,row_count:int,total_carton:int,received_carton:int,balance_carton:int,incomplete_count:int,is_complete:bool}
     */
    public function completion(PackingList $packingList, ?ImportBatch $batch = null): array
    {
        $batch = $batch ?: $this->activeBatch($packingList);

        if (!$batch) {
            return [
                'batch'            => null,
                'rows'             => collect(),
                'row_count'        => 0,
                'total_carton'     => 0,
                'received_carton'  => 0,
                'balance_carton'   => 0,
                'incomplete_count' => 0,
                'is_complete'      => false,
            ];
        }

        $rows = $this->reportRows($batch);

        $totalCarton    = (int) $rows->sum('packing_list_qty_carton');
        $receivedCarton = (int) $rows->sum('receive_qty_carton');
        $balanceCarton  = (int) $rows->sum('balance');
        $incomplete     = $rows->filter(function ($row) {
            return (int) $row->balance > 0
                || (int) $row->receive_qty_carton < (int) $row->packing_list_qty_carton;
        })->count();

        return [
            'batch'            => $batch,
            'rows'             => $rows,
            'row_count'        => $rows->count(),
            'total_carton'     => $totalCarton,
            'received_carton'  => $receivedCarton,
            'balance_carton'   => $balanceCarton,
            'incomplete_count' => $incomplete,
            'is_complete'      => $rows->isNotEmpty() && $incomplete === 0,
        ];
    }

    /**
     * Snapshot final yang disimpan di packing_lists.finished_snapshot.
     */
    public function buildSnapshot(PackingList $packingList, ImportBatch $batch, ?User $user = null): array
    {
        $rows = $this->reportRows($batch);

        $mappedRows = $rows->values()->map(function (Jancode $row, int $index) {
            $carton = '-';
            if ($row->carton_no_from) {
                $carton = (! $row->carton_no_to || (int) $row->carton_no_to === (int) $row->carton_no_from)
                    ? (string) $row->carton_no_from
                    : $row->carton_no_from . '–' . $row->carton_no_to;
            }

            return [
                'no'                      => $index + 1,
                'jancode'                 => $row->jancode,
                'color'                   => $row->color ?: '-',
                'size'                    => $row->size ?: '-',
                'carton_no'               => $carton,
                'carton_no_from'          => $row->carton_no_from,
                'carton_no_to'            => $row->carton_no_to,
                'qty_per_carton'          => (int) $row->qty_per_carton,
                'packing_list_qty_carton' => (int) $row->packing_list_qty_carton,
                'receive_qty_carton'      => (int) $row->receive_qty_carton,
                'balance'                 => (int) $row->balance,
                'total_qty_pcs'           => (int) $row->total_qty_pcs,
                'status'                  => 'Lengkap',
            ];
        })->all();

        return [
            'version'    => 1,
            'finished_at'=> now()->format('Y-m-d H:i:s'),
            'finished_by'=> $user?->name ?: 'System',
            'session'    => [
                'id'   => $packingList->session_id,
                'code' => $packingList->session?->code,
            ],
            'packing_list' => [
                'id'          => $packingList->id,
                'label'       => $packingList->group_label ?: $packingList->group_key,
                'style'       => $packingList->style_number,
                'ocf'         => $packingList->ocf_number,
                'destination' => $packingList->destination,
                'warehouse'   => $packingList->warehouse,
                'supplier'    => $packingList->supplier_name,
            ],
            'revision' => [
                'id'           => $batch->id,
                'no'           => (int) ($batch->revision_no ?: 1),
                'label'        => $batch->revision_label,
                'doc_type'     => strtoupper($batch->doc_type ?: 'proforma'),
                'batch_number' => $batch->batch_number,
                'import_date'  => $batch->import_date ? $batch->import_date->format('d/m/Y') : '-',
            ],
            'summary' => [
                'row_count'        => count($mappedRows),
                'total_carton'     => (int) $rows->sum('packing_list_qty_carton'),
                'received_carton'  => (int) $rows->sum('receive_qty_carton'),
                'balance_carton'   => (int) $rows->sum('balance'),
                'total_pcs'        => (int) $rows->sum('total_qty_pcs'),
                'incomplete_count' => 0,
            ],
            'rows' => $mappedRows,
        ];
    }
}
