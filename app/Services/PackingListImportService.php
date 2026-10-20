<?php

namespace App\Services;

use App\Models\ImportBatch;
use App\Models\ImportFailedRow;
use App\Models\ImportSession;
use App\Models\Jancode;
use App\Models\PackingList;

/**
 * Menyimpan hasil parsing satu sheet packing list menjadi satu REVISI
 * (ImportBatch) beserta baris jancode valid dan baris broken.
 *
 * Dipakai oleh controller import (web) maupun command demo (CLI).
 */
class PackingListImportService
{
    /**
     * @return array|null  null jika tidak ada data sama sekali
     */
    public function importSheet(array $sheet, array $meta = []): ?array
    {
        $header  = $sheet['header'] ?? [];
        $items   = $sheet['items'] ?? [];
        $skipped = $sheet['skipped'] ?? [];

        if (empty($items) && empty($skipped)) {
            return null;
        }

        $docType    = strtolower($sheet['doc_type'] ?? 'proforma') === 'actual' ? 'actual' : 'proforma';
        $groupKey   = ImportBatch::buildGroupKey($header);
        $groupLabel = ImportBatch::buildGroupLabel($header);

        // Session induk: semua import wajib berada dalam satu session.
        $sessionId = $meta['session_id'] ?? ImportSession::currentId();
        $session   = $sessionId ? ImportSession::find($sessionId) : null;

        if ($session && !$session->acceptsGroup($groupKey)) {
            throw new \InvalidArgumentException(
                'File ini memiliki style/OCF/destination/warehouse yang berbeda dari session aktif. Silakan buat session baru untuk packing list tersebut.'
            );
        }

        // Satu kombinasi style|OCF|destination|warehouse = satu session.
        // Jika kombinasi ini sudah dimiliki session lain, tolak agar packing
        // list antar session tidak tercampur.
        $owner = ImportSession::findByGroup($groupKey, $sessionId);
        if ($owner) {
            throw new \InvalidArgumentException(
                "Kombinasi {$groupLabel} sudah menjadi milik session {$owner->code}. "
                . 'Gunakan / buka kembali session tersebut agar data tidak tercampur dengan session lain.'
            );
        }

        $revisionNo  = ImportBatch::nextRevisionForGroup($groupKey, $sessionId);

        // Packing list = (session, group_key) — entitas utama yang stabil antar revisi.
        $packingList = PackingList::findOrCreateForGroupKey($groupKey, $sessionId);

        $totalCarton = array_sum(array_map(fn($i) => (int)($i['packing_list_qty_carton'] ?? 0), $items));

        $batch = ImportBatch::create([
            'session_id'        => $sessionId,
            'packing_list_id'   => $packingList?->id,
            'batch_number'      => $meta['batch_number'] ?? ImportBatch::generateBatchNumber(),
            'batch_name'        => ($meta['batch_name'] ?? null) ?: sprintf('%s %s', strtoupper($docType), $groupLabel),
            'doc_type'          => $docType,
            'original_filename' => $meta['original_filename'] ?? null,
            'sheet_name'        => $sheet['sheet_name'] ?? null,
            'supplier_name'     => ($meta['supplier_name'] ?? null) ?: ($header['buyer'] ?? null),
            'ocf_number'        => $header['ocf'] ?? null,
            'cpo'               => $header['ocf'] ?? null,
            'style_number'      => $header['style'] ?? null,
            'destination'       => $header['country'] ?? null,
            'warehouse'         => $header['warehouse'] ?? null,
            'group_key'         => $groupKey,
            'group_label'       => $groupLabel,
            'revision_no'       => $revisionNo,
            'is_active'         => true,
            'total_carton'      => $totalCarton,
            'import_date'       => now()->toDateString(),
            'status'            => 'processing',
            'total_rows'        => count($items) + count($skipped),
            'valid_rows'        => count($items),
            'invalid_rows'      => count($skipped),
            'total_items'       => count($items),
            'processed_items'   => 0,
            'imported_by'       => $meta['imported_by'] ?? auth()->id(),
        ]);

        $imported = $this->saveItems($batch, $items, $header);
        $this->saveBrokenRows($batch, $skipped);

        $status = 'completed';
        if (count($skipped) > 0 && $imported > 0) {
            $status = 'partial';
        } elseif ($imported === 0) {
            $status = 'failed';
        }

        $batch->update([
            'status'          => $status,
            'processed_items' => $imported,
            'valid_rows'      => $imported,
            'import_notes'    => sprintf(
                'Revisi %d %s — Baru: %d, Gagal: %d',
                $revisionNo,
                strtoupper($docType),
                $imported,
                count($skipped)
            ),
        ]);

        // Revisi baru membuat status FINISHED sebelumnya tidak berlaku lagi.
        // Report lama tetap tersimpan di file yang sudah diunduh, tetapi packing
        // list harus di-finish ulang setelah revisi ini selesai diproses.
        if ($packingList && $imported > 0 && $packingList->isFinished()) {
            $packingList->clearFinished();
        }

        // Ikat group ke session saat import pertama, lalu tentukan revisi aktif
        // (ACTUAL mengalahkan PROFORMA) dan bawa scan lama ke revisi aktif.
        $session?->bindGroup($groupKey, $header, $meta['supplier_name'] ?? null);
        (new PackingListRevisionService())->syncActiveRevision($groupKey, $sessionId);
        $batch->refresh();

        return [
            'batch'    => $batch,
            'imported' => $imported,
            'skipped'  => count($skipped),
        ];
    }

    /**
     * Simpan baris SOLID menjadi baris master JANCODE.
     *
     * Satu JAN + color + size bisa muncul di beberapa blok karton pada file
     * (rentang karton berbeda). Semua baris dengan identitas sama digabung
     * menjadi SATU baris master: quantity-nya dijumlahkan dan rentang karton
     * diambil dari yang terkecil sampai terbesar. Dengan begitu kapasitas
     * master = total carton packing list (tidak ada carton yang hilang).
     */
    private function saveItems(ImportBatch $batch, array $items, array $header): int
    {
        // 1. Gabungkan per identitas color|size|jan.
        $merged = [];

        foreach ($items as $item) {
            $jan = preg_replace('/[^0-9]/', '', (string)($item['jan_code'] ?? ''));
            if (strlen($jan) !== 13) {
                continue;
            }

            $packingQty = (int)($item['packing_list_qty_carton'] ?? 0);
            if ($packingQty <= 0) {
                continue;
            }

            $identity = strtolower(trim(($item['color'] ?? '') . '|' . ($item['size'] ?? '') . '|' . $jan));

            if (!isset($merged[$identity])) {
                $item['jan_code']                = $jan;
                $item['packing_list_qty_carton'] = $packingQty;
                $item['carton_no_from']          = $this->toIntOrNull($item['carton_no_from'] ?? null);
                $item['carton_no_to']            = $this->toIntOrNull($item['carton_no_to'] ?? null);
                $merged[$identity] = $item;
                continue;
            }

            $merged[$identity]['packing_list_qty_carton'] += $packingQty;

            $from    = $this->toIntOrNull($item['carton_no_from'] ?? null);
            $to      = $this->toIntOrNull($item['carton_no_to'] ?? null);
            $curFrom = $merged[$identity]['carton_no_from'];
            $curTo   = $merged[$identity]['carton_no_to'];

            if ($from !== null) {
                $merged[$identity]['carton_no_from'] = $curFrom === null ? $from : min($curFrom, $from);
            }
            if ($to !== null) {
                $merged[$identity]['carton_no_to'] = $curTo === null ? $to : max($curTo, $to);
            }
        }

        // 2. Simpan satu baris master per identitas.
        $imported = 0;

        foreach ($merged as $item) {
            $packingQty   = (int)$item['packing_list_qty_carton'];
            $qtyPerCarton = (int)($item['qty_per_carton'] ?? 0);

            Jancode::create([
                'import_batch_id'         => $batch->id,
                'packing_list_id'         => $batch->packing_list_id,
                'source_row'              => isset($item['source_row']) ? (int)$item['source_row'] : null,
                'country'                 => $header['country'] ?? null,
                'buyer'                   => $header['buyer'] ?? null,
                'ocf'                     => $header['ocf'] ?? null,
                'style'                   => $header['style'] ?? null,
                'carton_no_from'          => $item['carton_no_from'],
                'carton_no_to'            => $item['carton_no_to'],
                'color'                   => $item['color'] ?? null,
                'size'                    => $item['size'] ?? null,
                'description'             => trim(($item['color'] ?? '') . ' / ' . ($item['size'] ?? ''), ' /'),
                'qty_per_carton'          => $qtyPerCarton,
                'jancode'                 => $item['jan_code'],
                'packing_list_qty_carton' => $packingQty,
                'receive_qty_carton'      => 0,
                'balance'                 => $packingQty,
                'total_qty_pcs'           => $packingQty * $qtyPerCarton,
                'nw_per_carton'           => $item['nw_per_carton'] ?? null,
                'ttl_nw'                  => $item['ttl_nw'] ?? null,
                'gw_per_carton'           => $item['gw_per_carton'] ?? null,
                'ttl_gw'                  => $item['ttl_gw'] ?? null,
                'measurement_cm'          => $item['measurement_cm'] ?? null,
                'cbm'                     => $item['cbm'] ?? null,
                'qty'                     => $packingQty,
                'destination'             => $header['country'] ?? null,
                'cpo'                     => $header['ocf'] ?? null,
                'void'                    => 'false',
            ]);

            $imported++;
        }

        return $imported;
    }

    private function toIntOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $digits = preg_replace('/[^0-9]/', '', (string) $value);

        return $digits === '' ? null : (int) $digits;
    }

    /**
     * Simpan baris rusak / broken ke tabel terpisah (tetap bisa dilihat di
     * halaman detail packing list).
     */
    private function saveBrokenRows(ImportBatch $batch, array $skipped): void
    {
        $reasonLabels = ImportFailedRow::$reasonLabels;

        foreach ($skipped as $skip) {
            ImportFailedRow::create([
                'import_batch_id' => $batch->id,
                'source_row'      => (string)($skip['source_row'] ?? ''),
                'reason_code'     => $skip['reason'] ?? 'OTHER',
                'reason_label'    => $skip['reason_label'] ?? ($reasonLabels[$skip['reason'] ?? 'OTHER'] ?? ($skip['reason'] ?? 'Unknown')),
                'jan_code'        => $skip['jan_code'] ?? null,
                'color'           => $skip['color'] ?? null,
                'size'            => $skip['size'] ?? null,
                'keterangan'      => $skip['keterangan'] ?? null,
                'total_carton'    => isset($skip['total_carton']) ? (int)$skip['total_carton'] : null,
                'carton_merged'   => (bool)($skip['carton_merged'] ?? false),
                'raw_data'        => isset($skip['raw_data']) ? $skip['raw_data'] : $skip,
                'notes'           => $skip['notes'] ?? null,
            ]);
        }
    }
}
