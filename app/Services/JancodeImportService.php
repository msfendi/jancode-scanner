<?php

namespace App\Services;

use App\Support\JancodeCode;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class JancodeImportService
{
    /**
     * Batas aman area pemindaian. Beberapa file Excel menyimpan sel dengan
     * gaya (style) jauh di luar area data sehingga Worksheet::getHighestRow()
     * / getHighestColumn() melaporkan dimensi raksasa (mis. baris 1.048.576,
     * kolom WVR). Tanpa clamp, iterasi akan menghabiskan memori.
     */
    private const MAX_SCAN_ROW = 5000;
    private const MAX_SCAN_COL_INDEX = 60;

    /**
     * Parse packing list excel file and return structured preview.
     * Tetap dipertahankan sebagai alias dari parseWorkbook().
     */
    public function parseFile(string $filePath): array
    {
        return $this->parseWorkbook($filePath);
    }

    /**
     * Parse SELURUH sheet pada workbook. Setiap sheet yang berjudul
     * "PACKING LIST ..." dianggap satu kandidat packing list (satu revisi).
     * Sheet non-data (DETAIL ASSORT, PEMBAGIAN, GLOBAL, dsb) otomatis dilewati.
     */
    public function parseWorkbook(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);

        $aiMode = (bool) (config('services.gemini.api_key') || env('GEMINI_API_KEY') || env('OPENAI_API_KEY'));

        $sheets       = [];
        $mergedItems  = [];
        $mergedSkipped = [];

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $title = $this->sheetTitle($sheet);

            // Hanya proses sheet yang benar-benar packing list
            if (!str_contains($title, 'PACKING LIST') && !str_contains($title, 'PACKING  LIST')) {
                continue;
            }

            // 1. Ekstrak Header metadata (BUYER, STYLE, DESTINATION/COUNTRY, CPO/OCD, WAREHOUSE)
            $header = $this->extractHeader($sheet);

            // 2. Deteksi layout kolom tabel secara dinamis
            $layout = $this->detectTableLayout($sheet);

            // 3. Pre-processing: Deteksi merge cells & forward fill
            $preprocessed = $this->preprocessRows($sheet, $layout);

            // 4. Ekstraksi deterministik (Ground Truth Engine)
            $deterministicResult = $this->deterministicExtract($header, $preprocessed['rows'], $layout);

            // 5. AI Calling (opsional, hanya jika API key tersedia)
            $finalResult = $deterministicResult;
            if ($aiMode) {
                try {
                    $aiResult = $this->callAiParser($header, $preprocessed['structured_text']);
                    if ($aiResult && !empty($aiResult['items'])) {
                        $finalResult = $this->validateAiResult($aiResult, $deterministicResult, $header);
                    }
                } catch (\Throwable $e) {
                    Log::warning('AI parsing error, falling back to deterministic: ' . $e->getMessage());
                    $finalResult = $deterministicResult;
                }
            }

            $docType     = $this->detectDocType($title);
            $sheetName   = $sheet->getTitle();
            $items       = $finalResult['items'];
            $skipped     = $finalResult['skipped'];

            foreach ($items as &$it) {
                $it['sheet_name'] = $sheetName;
                $it['doc_type']   = $docType;
            }
            unset($it);

            $sheetCarton      = array_sum(array_column($items, 'packing_list_qty_carton'));
            $expectedCarton   = array_sum(array_column($deterministicResult['items'], 'packing_list_qty_carton'));

            $sheets[] = [
                'sheet_name'     => $sheetName,
                'doc_type'       => $docType,
                'title'          => $title,
                'header'         => $header,
                'items'          => $items,
                'skipped'        => $skipped,
                'total_items'    => count($items),
                'total_carton'   => $sheetCarton,
                'skipped_count'  => count($skipped),
                'carton_match'   => ($sheetCarton === $expectedCarton),
                'expected_carton'=> $expectedCarton,
            ];

            $mergedItems   = array_merge($mergedItems, $items);
            $mergedSkipped = array_merge($mergedSkipped, $skipped);
        }

        $firstHeader = $sheets[0]['header'] ?? [
            'buyer' => 'UNKNOWN', 'style' => 'UNKNOWN', 'country' => 'UNKNOWN',
            'ocf' => 'UNKNOWN', 'warehouse' => '',
        ];

        $totalCarton = array_sum(array_column($mergedItems, 'packing_list_qty_carton'));
        $expected    = 0;
        foreach ($sheets as $s) {
            $expected += $s['expected_carton'];
        }

        return [
            'header'          => $firstHeader,
            'layout'          => [],
            'items'           => $mergedItems,
            'skipped'         => $mergedSkipped,
            'sheets'          => $sheets,
            'total_items'     => count($mergedItems),
            'total_carton'    => $totalCarton,
            'skipped_count'   => count($mergedSkipped),
            'carton_match'    => ($totalCarton === $expected),
            'expected_carton' => $expected,
            'ai_mode'         => $aiMode,
        ];
    }

    /**
     * Gabungan teks baris 1-3 (kolom A..N) untuk mendeteksi judul sheet.
     */
    private function sheetTitle($sheet): string
    {
        $parts = [];
        for ($row = 1; $row <= 3; $row++) {
            for ($col = 1; $col <= 14; $col++) {
                $val = $this->getCellString($sheet, Coordinate::stringFromColumnIndex($col), $row);
                if ($val !== '') {
                    $parts[] = $val;
                }
            }
        }

        return strtoupper(preg_replace('/\s+/', ' ', implode(' ', $parts)));
    }

    /**
     * Tentukan jenis dokumen dari judul sheet: actual atau proforma.
     */
    private function detectDocType(string $title): string
    {
        return str_contains($title, 'ACTUAL') ? 'actual' : 'proforma';
    }

    /**
     * Helper aman membaca nilai sel agar tidak crash jika ada formula error / #VALUE!
     */
    private function getCellValue($sheet, string $colOrCoord, ?int $row = null): mixed
    {
        $coord = $row !== null ? "{$colOrCoord}{$row}" : $colOrCoord;
        try {
            $cell = $sheet->getCell($coord);
            if (!$cell) return null;
            return $cell->getCalculatedValue();
        } catch (\Throwable $e) {
            try {
                return $sheet->getCell($coord)->getValue();
            } catch (\Throwable $e2) {
                return null;
            }
        }
    }

    /**
     * Helper aman membaca string teks sel
     */
    private function getCellString($sheet, string $colOrCoord, ?int $row = null): string
    {
        $val = $this->getCellValue($sheet, $colOrCoord, $row);
        return trim((string)($val ?? ''));
    }

    /**
     * Ekstrak Header: BUYER, STYLE, DESTINATION/COUNTRY, CPO/OCD/OCF secara dinamis.
     * Mencari label pada baris 1-30 di kolom manapun, lalu mengambil nilainya dari sel tersebut
     * atau sel di kanannya (termasuk jika dipisahkan tanda titik dua / merge).
     */
    private function extractHeader($sheet): array
    {
        $highestRow = min(30, $sheet->getHighestRow());
        $highestColIndex = min(25, Coordinate::columnIndexFromString($sheet->getHighestColumn()));

        $buyer = '';
        $style = '';
        $country = '';
        $ocf = '';
        $warehouse = '';

        for ($row = 1; $row <= $highestRow; $row++) {
            for ($col = 1; $col <= $highestColIndex; $col++) {
                $colLetter = Coordinate::stringFromColumnIndex($col);
                $cellVal = $this->getCellString($sheet, $colLetter, $row);
                if ($cellVal === '') continue;

                $upper = strtoupper($cellVal);

                // Helper ambil nilai di sebelah kanan (kolom berikutnya).
                // Lewati sel pemisah seperti ":" atau "-" agar nilai asli terbaca.
                $getNextVal = function() use ($sheet, $col, $row, $highestColIndex) {
                    for ($next = $col + 1; $next <= min($highestColIndex, $col + 4); $next++) {
                        $nextLetter = Coordinate::stringFromColumnIndex($next);
                        $val = trim($this->getCellString($sheet, $nextLetter, $row));
                        if ($val !== '' && $val !== ':' && $val !== '-') {
                            return $val;
                        }
                    }
                    return '';
                };

                // Helper bersihkan prefix label mis. "BUYER : MUJI" -> "MUJI"
                $cleanLabel = function($val) {
                    $parts = preg_split('/[:\-]/', $val, 2);
                    return isset($parts[1]) ? trim($parts[1]) : '';
                };

                if (str_contains($upper, 'BUYER') && !$buyer) {
                    $val = $cleanLabel($cellVal) ?: $getNextVal();
                    if ($val) $buyer = $val;
                } elseif (str_contains($upper, 'STYLE') && !$style) {
                    $val = $cleanLabel($cellVal) ?: $getNextVal();
                    if ($val) $style = $val;
                } elseif ((str_contains($upper, 'DESTINATION') || str_contains($upper, 'COUNTRY')) && !$country) {
                    $val = $cleanLabel($cellVal) ?: $getNextVal();
                    if ($val) $country = strtoupper(trim($val));
                } elseif ((str_contains($upper, 'CPO') || str_contains($upper, 'OCD') || str_contains($upper, 'ORDER NO') || str_contains($upper, 'OCF')) && !$ocf) {
                    $val = $cleanLabel($cellVal) ?: $getNextVal();
                    if ($val) {
                        // Nilai CPO bisa berisi tambahan, mis. "25143 / 266C0067 - B3".
                        // Ambil hanya nomor utama (sebelum pemisah / atau -).
                        $parts = preg_split('/[\/\-]/', $val);
                        $ocf = trim($parts[0] ?? $val);
                    }
                } elseif ((str_contains($upper, 'WAREHOUSE') || str_contains($upper, 'WAREHAOUSE') || str_contains($upper, 'GUDANG')) && !$warehouse) {
                    $val = $cleanLabel($cellVal) ?: $getNextVal();
                    if ($val) $warehouse = strtoupper(trim($val));
                }
            }
        }

        return [
            'buyer'     => $buyer ?: 'UNKNOWN',
            'style'     => $style ?: 'UNKNOWN',
            'country'   => $country ?: 'UNKNOWN',
            'ocf'       => $ocf ?: 'UNKNOWN',
            'warehouse' => $warehouse,
        ];
    }

    /**
     * Deteksi posisi kolom tabel secara dinamis dari baris header tabel.
     * Tidak lagi bergantung pada huruf kolom statis (A, D, E, F, G..L, N).
     */
    private function detectTableLayout($sheet): array
    {
        $highestRow = min(35, $sheet->getHighestRow());
        $highestColIndex = min(self::MAX_SCAN_COL_INDEX, Coordinate::columnIndexFromString($sheet->getHighestColumn()));

        $bestHeaderRow = null;
        $bestScore = 0;

        // Cari baris yang paling banyak mengandung kata kunci header packing list (mulai dari baris 2)
        for ($row = 2; $row <= $highestRow; $row++) {
            $score = 0;
            for ($col = 1; $col <= $highestColIndex; $col++) {
                $colLetter = Coordinate::stringFromColumnIndex($col);
                $val = strtoupper($this->getCellString($sheet, $colLetter, $row));
                if ($val !== '') {
                    if (str_contains($val, 'JAN') || str_contains($val, 'BARCODE') || str_contains($val, 'EAN')) $score += 10;
                    if (str_contains($val, 'COLOR') || str_contains($val, 'COLOUR') || str_contains($val, 'WARNA')) $score += 8;
                    if (str_contains($val, 'CARTON') || str_contains($val, 'CTN')) $score += 6;
                    if (str_contains($val, 'TOTAL CARTON') || str_contains($val, 'TTL CTN') || str_contains($val, 'CARTON QTY')) $score += 8;
                    if (str_contains($val, 'TOTAL PCS') || str_contains($val, 'TTL PCS')) $score += 6;
                    if (in_array($val, ['XS', 'S', 'M', 'L', 'XL', '2XL', 'XXL', '3XL', 'FREE'])) $score += 4;
                    if (str_contains($val, 'KETERANGAN') || str_contains($val, 'REMARK') || str_contains($val, 'TYPE')) $score += 4;
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestHeaderRow = $row;
            }
        }

        $headerRow = $bestHeaderRow ?: 11;
        $prevRow = max(1, $headerRow - 1);

        $layout = [
            'header_row'        => $headerRow,
            'data_start_row'    => $headerRow + 1,
            'carton_from_col'   => null,
            'carton_to_col'     => null,
            'color_col'         => null,
            'jan_col'           => null,
            'keterangan_col'    => null, // OPTIONAL: Bisa null jika tidak ada kolom Keterangan!
            'size_cols'         => [],   // [colLetter => sizeName]
            'size_row'          => null, // Baris tempat nama size berada (jika di bawah header)
            'single_size_col'   => null, // Jika size ditaruh di 1 kolom teks
            'total_carton_col'  => null,
            'total_pcs_col'     => null,
            'qty_per_carton_col'=> null,
            'nw_col'            => null,
            'ttl_nw_col'        => null,
            'gw_col'            => null,
            'ttl_gw_col'        => null,
            'measurement_col'   => null,
            'cbm_col'           => null,
        ];

        $standardSizes = ['XS', 'S', 'M', 'L', 'XL', '2XL', 'XXL', '3XL', 'XXXL', '4XL', '28', '29', '30', '31', '32', '33', '34', '36', '38', '40', 'FREE', 'ALL'];

        // Telusuri kolom-kolom pada headerRow
        for ($col = 1; $col <= $highestColIndex; $col++) {
            $colLetter = Coordinate::stringFromColumnIndex($col);
            $val = strtoupper($this->getCellString($sheet, $colLetter, $headerRow));
            $topVal = strtoupper($this->getCellString($sheet, $colLetter, $prevRow));

            // 1. JAN CODE
            if (str_contains($val, 'JAN') || str_contains($val, 'BARCODE') || str_contains($val, 'EAN')) {
                if (!$layout['jan_col']) $layout['jan_col'] = $colLetter;
                continue;
            }

            // 2. COLOR
            if (str_contains($val, 'COLOR') || str_contains($val, 'COLOUR') || str_contains($val, 'WARNA')) {
                if (!$layout['color_col']) $layout['color_col'] = $colLetter;
                continue;
            }

            // 3. KETERANGAN (Opsional)
            if (str_contains($val, 'KETERANGAN') || str_contains($val, 'REMARK') || str_contains($val, 'PACKING TYPE') || $val === 'TYPE') {
                if (!$layout['keterangan_col']) $layout['keterangan_col'] = $colLetter;
                continue;
            }

            // 4. TOTAL CARTON
            if (
                str_contains($val, 'TOTAL CARTON') ||
                str_contains($val, 'TOTAL CTN') ||
                str_contains($val, 'TTL CTN') ||
                str_contains($val, 'CARTON QTY') ||
                str_contains($val, 'QTY CTN') ||
                ($val === 'TOTAL' && str_contains($topVal, 'CARTON'))
            ) {
                if (!$layout['total_carton_col']) $layout['total_carton_col'] = $colLetter;
                continue;
            }

            // 5. TOTAL PCS
            if (
                str_contains($val, 'TOTAL PCS') ||
                str_contains($val, 'TTL PCS') ||
                str_contains($val, 'TOTAL QTY') ||
                str_contains($val, 'QTY PCS') ||
                ($val === 'TOTAL' && str_contains($topVal, 'PCS'))
            ) {
                if (!$layout['total_pcs_col']) $layout['total_pcs_col'] = $colLetter;
                continue;
            }

            // 6. QTY / CTN (Pcs per karton)
            if (
                str_contains($val, 'PCS/CTN') ||
                str_contains($val, 'QTY/CTN') ||
                str_contains($val, 'PCS PER CARTON') ||
                str_contains($val, 'QTY PER CARTON') ||
                str_contains($val, 'TOTAL PCS/CTN')
            ) {
                if (!$layout['qty_per_carton_col']) $layout['qty_per_carton_col'] = $colLetter;
                continue;
            }

            // 7. SIZE BREAKDOWN atau SINGLE SIZE
            if (in_array($val, $standardSizes) || (is_numeric($val) && (int)$val >= 20 && (int)$val <= 60)) {
                $layout['size_cols'][$colLetter] = $val;
                continue;
            }

            if ($val === 'SIZE' || str_contains($val, 'UKURAN')) {
                $layout['single_size_col'] = $colLetter;
                continue;
            }

            // 8. CARTON NUMBERING (From / To)
            if (str_contains($val, 'CARTON') || str_contains($val, 'CTN') || str_contains($val, 'CTNS') || str_contains($val, 'BOX')) {
                if (!$layout['carton_from_col'] && !str_contains($val, 'TOTAL') && !str_contains($val, 'QTY')) {
                    $layout['carton_from_col'] = $colLetter;
                    continue;
                }
            }
            if ($val === 'TO' || str_contains($val, 'CTN TO') || str_contains($val, 'CARTON TO')) {
                if (!$layout['carton_to_col']) $layout['carton_to_col'] = $colLetter;
                continue;
            }

            // 9. WEIGHTS & MEASUREMENTS
            if (str_contains($val, 'N.W') || str_contains($val, 'NW') || str_contains($val, 'NET WEIGHT')) {
                if (str_contains($val, 'TTL') || str_contains($val, 'TOTAL')) {
                    $layout['ttl_nw_col'] = $colLetter;
                } else {
                    $layout['nw_col'] = $colLetter;
                }
                continue;
            }
            if (str_contains($val, 'G.W') || str_contains($val, 'GW') || str_contains($val, 'GROSS WEIGHT')) {
                if (str_contains($val, 'TTL') || str_contains($val, 'TOTAL')) {
                    $layout['ttl_gw_col'] = $colLetter;
                } else {
                    $layout['gw_col'] = $colLetter;
                }
                continue;
            }
            if (str_contains($val, 'MEAS') || str_contains($val, 'DIMENSION')) {
                $layout['measurement_col'] = $colLetter;
                continue;
            }
            if (str_contains($val, 'CBM') || str_contains($val, 'M3') || str_contains($val, 'VOLUME')) {
                $layout['cbm_col'] = $colLetter;
                continue;
            }
        }

        // Fallback default cerdas jika header tidak memakai label teks baku
        if (!$layout['carton_from_col']) $layout['carton_from_col'] = 'A';
        if (!$layout['carton_to_col']) $layout['carton_to_col'] = 'C';
        if (!$layout['color_col']) $layout['color_col'] = 'D';
        if (!$layout['jan_col']) $layout['jan_col'] = 'E';
        if (!$layout['total_carton_col']) $layout['total_carton_col'] = 'N';

        // Jika size_cols masih kosong (header "BREAKDOWN SIZE" di-merge 2 baris),
        // cari baris di bawah headerRow yang memuat nama-nama size.
        if (empty($layout['size_cols']) && !$layout['single_size_col']) {
            $reserved = array_values(array_filter([
                $layout['jan_col'], $layout['color_col'], $layout['keterangan_col'],
                $layout['total_carton_col'], $layout['total_pcs_col'],
                $layout['qty_per_carton_col'], $layout['nw_col'], $layout['ttl_nw_col'],
                $layout['gw_col'], $layout['ttl_gw_col'], $layout['measurement_col'],
                $layout['cbm_col'], $layout['carton_from_col'], $layout['carton_to_col'],
            ]));

            $isSizeToken = function (string $val) use ($standardSizes): bool {
                return in_array($val, $standardSizes, true)
                    || (is_numeric($val) && (int) $val >= 20 && (int) $val <= 60);
            };

            $sizeRow = null;
            $best = 0;
            $scanTo = min($headerRow + 2, self::MAX_SCAN_ROW);

            for ($r = $headerRow; $r <= $scanTo; $r++) {
                $count = 0;
                for ($col = 1; $col <= $highestColIndex; $col++) {
                    $colLetter = Coordinate::stringFromColumnIndex($col);
                    if (in_array($colLetter, $reserved, true)) continue;
                    $val = strtoupper($this->getCellString($sheet, $colLetter, $r));
                    if ($val !== '' && $isSizeToken($val)) $count++;
                }
                if ($count > $best) {
                    $best = $count;
                    $sizeRow = $r;
                }
            }

            // Butuh minimal 2 nama size agar tidak salah mengira baris data sebagai header.
            if ($sizeRow !== null && $best >= 2) {
                for ($col = 1; $col <= $highestColIndex; $col++) {
                    $colLetter = Coordinate::stringFromColumnIndex($col);
                    if (in_array($colLetter, $reserved, true)) continue;
                    $val = strtoupper($this->getCellString($sheet, $colLetter, $sizeRow));
                    if ($val !== '' && $isSizeToken($val)) {
                        $layout['size_cols'][$colLetter] = $val;
                    }
                }

                $layout['size_row'] = $sizeRow;
                $layout['data_start_row'] = max($layout['data_start_row'], $sizeRow + 1);
            }
        }

        return $layout;
    }

    /**
     * Membaca merge cell dan menyusun baris terstruktur berdasarkan layout kolom dinamis.
     */
    private function preprocessRows($sheet, array $layout): array
    {
        $mergeRanges = $sheet->getMergeCells();
        $mergedRowMap = [];   // [col][row] => is_multi_row_merged
        $mergedValueMap = []; // [col][row] => cell_value_from_top_left
        $cartonGroupMap = []; // [row] => "startRow-endRow"

        $cartonColumns = array_filter([
            $layout['carton_from_col'],
            $layout['carton_to_col'],
            $layout['total_carton_col']
        ]);

        foreach ($mergeRanges as $range) {
            [$start, $end] = Coordinate::splitRange($range)[0];
            $startCol = preg_replace('/[0-9]/', '', $start);
            $startRow = (int) preg_replace('/[^0-9]/', '', $start);
            $endCol = preg_replace('/[0-9]/', '', $end);
            $endRow = (int) preg_replace('/[^0-9]/', '', $end);

            $topVal = $this->getCellString($sheet, "{$startCol}{$startRow}");
            $startColIndex = Coordinate::columnIndexFromString($startCol);
            $endColIndex = Coordinate::columnIndexFromString($endCol);

            for ($c = $startColIndex; $c <= $endColIndex; $c++) {
                $colLetter = Coordinate::stringFromColumnIndex($c);
                for ($r = $startRow; $r <= $endRow; $r++) {
                    if ($topVal !== '') {
                        $mergedValueMap[$colLetter][$r] = $topVal;
                    }
                    if ($endRow > $startRow) {
                        $mergedRowMap[$colLetter][$r] = true;
                        if (in_array($colLetter, $cartonColumns)) {
                            $cartonGroupMap[$r] = "{$startRow}-{$endRow}";
                        }
                    }
                }
            }
        }

        $highestRow = min(self::MAX_SCAN_ROW, $sheet->getHighestRow());
        $rows = [];
        $currentColor = '';
        $currentKeterangan = '';
        $structuredTextLines = [];

        $startRow = $layout['data_start_row'];
        $colorCol = $layout['color_col'] ?: 'D';
        $janCol = $layout['jan_col'] ?: 'E';
        $ketCol = $layout['keterangan_col']; // Boleh null jika tidak ada kolom Keterangan!
        $totalCtnCol = $layout['total_carton_col'] ?: 'N';
        $ctnFromCol = $layout['carton_from_col'] ?: 'A';
        $ctnToCol = $layout['carton_to_col'] ?: 'C';
        $sizeHeaders = $layout['size_cols'];
        $singleSizeCol = $layout['single_size_col'];

        for ($row = $startRow; $row <= $highestRow; $row++) {
            // 1. Resolve COLOR dengan Merge Map + Forward Fill
            $rawColor = $mergedValueMap[$colorCol][$row] ?? $this->getCellString($sheet, $colorCol, $row);
            if (!empty($rawColor)) {
                $upperColor = strtoupper(trim($rawColor));
                if (!str_contains($upperColor, 'TOTAL') && !str_contains($upperColor, 'GRAND') && !str_contains($upperColor, 'QTY')) {
                    $currentColor = $upperColor;
                }
            }

            // 2. Resolve KETERANGAN (jika ada kolomnya di Excel)
            if ($ketCol) {
                $rawKeterangan = $mergedValueMap[$ketCol][$row] ?? $this->getCellString($sheet, $ketCol, $row);
                if (!empty($rawKeterangan)) {
                    $upperKet = strtoupper(trim($rawKeterangan));
                    if (str_contains($upperKet, 'SOLID')) {
                        $currentKeterangan = 'SOLID';
                    } elseif (str_contains($upperKet, 'ASSORT')) {
                        $currentKeterangan = 'ASSORT PACK';
                    } else {
                        $currentKeterangan = $upperKet;
                    }
                }
            } else {
                $currentKeterangan = ''; // Tidak ada kolom keterangan di template ini
            }

            // 3. Flag carton_merged: true jika sel nomor karton atau total karton masuk merge range multi-baris
            $cartonMerged = false;
            foreach ($cartonColumns as $cCol) {
                if (!empty($mergedRowMap[$cCol][$row])) {
                    $cartonMerged = true;
                    break;
                }
            }

            // 4. JAN CODE
            $janCode = $this->getCellString($sheet, $janCol, $row);
            if (empty($janCode) && !empty($mergedValueMap[$janCol][$row])) {
                $janCode = trim($mergedValueMap[$janCol][$row]);
            }

            // 5. TOTAL CARTON
            $totalCartonVal = $this->getCellValue($sheet, $totalCtnCol, $row);
            if ($totalCartonVal === null && !empty($mergedValueMap[$totalCtnCol][$row])) {
                $totalCartonVal = $mergedValueMap[$totalCtnCol][$row];
            }

            // 6. BREAKDOWN SIZE atau SINGLE SIZE
            $sizesFilled = [];
            if (!empty($sizeHeaders)) {
                foreach ($sizeHeaders as $col => $sizeName) {
                    $val = (int) $this->getCellValue($sheet, $col, $row);
                    if ($val > 0) {
                        $sizesFilled[$sizeName] = $val;
                    }
                }
            } elseif ($singleSizeCol) {
                $sVal = $this->getCellString($sheet, $singleSizeCol, $row);
                if ($sVal !== '') {
                    $sizesFilled[$sVal] = 1;
                }
            }

            // 7. Simpan data mentah baris & atribut fisik karton
            $colAVal = $this->getCellString($sheet, $ctnFromCol, $row);
            $colCVal = $ctnToCol ? $this->getCellString($sheet, $ctnToCol, $row) : '';
            $cartonFromNum = preg_replace('/[^0-9]/', '', $colAVal);
            $cartonToNum = preg_replace('/[^0-9]/', '', $colCVal);

            $nwVal = $layout['nw_col'] ? $this->getCellValue($sheet, $layout['nw_col'], $row) : null;
            $ttlNwVal = $layout['ttl_nw_col'] ? $this->getCellValue($sheet, $layout['ttl_nw_col'], $row) : null;
            $gwVal = $layout['gw_col'] ? $this->getCellValue($sheet, $layout['gw_col'], $row) : null;
            $ttlGwVal = $layout['ttl_gw_col'] ? $this->getCellValue($sheet, $layout['ttl_gw_col'], $row) : null;
            $measVal = $layout['measurement_col'] ? $this->getCellString($sheet, $layout['measurement_col'], $row) : null;
            $cbmVal = $layout['cbm_col'] ? $this->getCellValue($sheet, $layout['cbm_col'], $row) : null;

            $rowData = [
                'row'             => $row,
                'carton_merged'   => $cartonMerged,
                'carton_group'    => $cartonGroupMap[$row] ?? null,
                'carton_no_from'  => $cartonFromNum !== '' ? (int)$cartonFromNum : null,
                'carton_no_to'    => $cartonToNum !== '' ? (int)$cartonToNum : null,
                'color_inherited' => $currentColor,
                'jan_code'        => $janCode,
                'keterangan'      => $currentKeterangan,
                'sizes_filled'    => $sizesFilled,
                'total_carton'    => is_numeric($totalCartonVal) ? (int)$totalCartonVal : null,
                'nw_per_carton'   => is_numeric($nwVal) ? (float)$nwVal : null,
                'ttl_nw'          => is_numeric($ttlNwVal) ? (float)$ttlNwVal : null,
                'gw_per_carton'   => is_numeric($gwVal) ? (float)$gwVal : null,
                'ttl_gw'          => is_numeric($ttlGwVal) ? (float)$ttlGwVal : null,
                'measurement_cm'  => $measVal ?: null,
                'cbm'             => is_numeric($cbmVal) ? (float)$cbmVal : null,
                'col_a'           => $colAVal,
                'col_d'           => $rawColor,
            ];

            $rows[] = $rowData;

            // Structured text format untuk AI
            $sizesStr = implode(', ', array_map(fn($k, $v) => "$k:$v", array_keys($sizesFilled), $sizesFilled));
            $structuredTextLines[] = sprintf(
                "Row %d | CartMerged: %s | Color: %s | JAN: %s | Ket: %s | Sizes: [%s] | TotalCarton: %s | ColA: %s",
                $row,
                $cartonMerged ? 'true' : 'false',
                $currentColor,
                $janCode,
                $currentKeterangan,
                $sizesStr,
                $totalCartonVal !== null ? (string)$totalCartonVal : '',
                $rowData['col_a']
            );
        }

        return [
            'rows'            => $rows,
            'structured_text' => implode("\n", $structuredTextLines),
        ];
    }

    /**
     * Mesin ekstraksi deterministik berdasarkan aturan PRD (R1 - R6).
     * Mampu mendeteksi SOLID secara akurat meskipun kolom KETERANGAN tidak ada.
     */
    public function deterministicExtract(array $header, array $rows, array $layout = []): array
    {
        $items = [];
        $skipped = [];
        $processedCartonGroups = [];

        $hasKeteranganCol = !empty($layout['keterangan_col']);

        // Pra-pemindaian untuk mendeteksi karton BROKEN:
        // jancode yang muncul lebih dari sekali (bukan bagian carton merge dan
        // bukan assort) lalu salah satu barisnya punya qty pcs di bawah ambang.
        $brokenThreshold = (int) config('packing.broken_pcs_threshold', 60);
        $standaloneJanStats = []; // [jan => ['rows' => n, 'max_pcs' => n]]

        foreach ($rows as $statRow) {
            if (!empty($statRow['carton_group']) || !empty($statRow['carton_merged'])) {
                continue;
            }

            if (count($statRow['sizes_filled'] ?? []) !== 1) {
                continue;
            }

            if (str_contains(strtoupper((string) ($statRow['keterangan'] ?? '')), 'ASSORT')) {
                continue;
            }

            $statJan = preg_replace('/[^0-9]/', '', (string) ($statRow['jan_code'] ?? ''));

            if (strlen($statJan) !== JancodeCode::LENGTH) {
                continue;
            }

            $statPcs = (int) array_sum($statRow['sizes_filled'] ?? []);

            if (!isset($standaloneJanStats[$statJan])) {
                $standaloneJanStats[$statJan] = ['rows' => 0, 'max_pcs' => 0];
            }

            $standaloneJanStats[$statJan]['rows']++;
            $standaloneJanStats[$statJan]['max_pcs'] = max($standaloneJanStats[$statJan]['max_pcs'], $statPcs);
        }

        foreach ($rows as $r) {
            $rowNum = $r['row'];
            $keterangan = $r['keterangan'];
            $jan = $r['jan_code'];
            $colA = strtoupper($r['col_a']);
            $cleanJan = preg_replace('/[^0-9]/', '', $jan);
            $groupKey = $r['carton_group'] ?? null;

            // Lewati baris kosong pemisah (tidak ada data sama sekali)
            if (empty($cleanJan) && empty($r['sizes_filled']) && empty($r['total_carton']) && empty($colA)) {
                continue;
            }

            // R6: Bukan baris ringkasan
            if (
                str_contains($colA, 'TOTAL') ||
                str_contains($colA, 'QTY') ||
                str_contains($colA, 'GRAND TOTAL') ||
                str_contains($keterangan, 'TOTAL') ||
                str_contains(strtoupper($r['color_inherited']), 'TOTAL')
            ) {
                $skipped[] = [
                    'source_row'    => (string)$rowNum,
                    'reason'        => 'SUMMARY_ROW',
                    'reason_label'  => 'Baris ringkasan/total (TOTAL, QTY, GRAND TOTAL)',
                    'jan_code'      => $cleanJan ?: $jan,
                    'color'         => $r['color_inherited'],
                    'size'          => '-',
                    'keterangan'    => $keterangan ?: 'TOTAL',
                    'total_carton'  => $r['total_carton'],
                    'carton_merged' => (bool)$r['carton_merged'],
                    'raw_data'      => $r,
                    'notes'         => 'Diabaikan karena baris total',
                ];
                continue;
            }

            // Deteksi Karton Gabungan / Merge Qty / Assort yang memakai satu carton
            $isAssortByKet = ($keterangan === 'ASSORT PACK' || str_contains($keterangan, 'ASSORT'));

            if ($groupKey && ($r['carton_merged'] || $isAssortByKet)) {
                if (isset($processedCartonGroups[$groupKey])) {
                    continue;
                }
                $processedCartonGroups[$groupKey] = true;

                $groupRows = array_values(array_filter($rows, fn($item) => ($item['carton_group'] ?? null) === $groupKey));

                $jans = [];
                $colors = [];
                $sizes = [];
                $subRowsDetails = [];
                $cartonQty = null;

                foreach ($groupRows as $gr) {
                    $subCleanJan = preg_replace('/[^0-9]/', '', $gr['jan_code']);
                    if ($subCleanJan) {
                        $jans[] = $subCleanJan;
                    } elseif (!empty($gr['jan_code'])) {
                        $jans[] = $gr['jan_code'];
                    }

                    $subColor = trim($gr['color_inherited']);
                    if ($subColor && !in_array($subColor, $colors)) {
                        $colors[] = $subColor;
                    }

                    $sizeParts = [];
                    foreach ($gr['sizes_filled'] as $sz => $pcs) {
                        $sizeParts[] = "{$sz} ({$pcs} pcs)";
                    }
                    if (!empty($sizeParts)) {
                        $sizes[] = implode('/', $sizeParts);
                    }

                    if ($gr['total_carton'] !== null) {
                        $cartonQty = max((int) $cartonQty, (int) $gr['total_carton']);
                    }

                    $subRowsDetails[] = [
                        'row'      => $gr['row'],
                        'jan_code' => $subCleanJan ?: $gr['jan_code'],
                        'color'    => $subColor,
                        'sizes'    => $gr['sizes_filled'],
                        'pcs'      => array_sum($gr['sizes_filled']),
                    ];
                }

                $jans = array_unique($jans);
                $cartonQty = $cartonQty ?: 1;

                // CASE MIX JANCODE: 2 (atau lebih) jancode berbeda berada dalam
                // satu karton yang sama. Tetap diimport sebagai SATU baris dengan
                // jancode ditulis "jancode1-jancode2", supaya operator tetap bisa
                // scan salah satu jancode-nya.
                $distinctJans = array_values(array_unique(array_filter(
                    $jans,
                    fn ($jan): bool => is_string($jan) && strlen($jan) === JancodeCode::LENGTH
                )));

                // Gabungan size & total pcs per karton untuk seluruh baris grup.
                $sizeNames = [];
                $totalPcsPerCarton = 0;

                foreach ($groupRows as $gr) {
                    $totalPcsPerCarton += (int) array_sum($gr['sizes_filled']);

                    foreach (array_keys($gr['sizes_filled']) as $sizeName) {
                        if (!in_array($sizeName, $sizeNames, true)) {
                            $sizeNames[] = $sizeName;
                        }
                    }
                }

                $froms = array_values(array_filter(
                    array_map(fn ($gr) => $gr['carton_no_from'] ?? null, $groupRows),
                    fn ($v) => $v !== null
                ));
                $tos = array_values(array_filter(
                    array_map(fn ($gr) => $gr['carton_no_to'] ?? null, $groupRows),
                    fn ($v) => $v !== null
                ));

                $firstRow = $groupRows[0] ?? $r;
                $itemBase = [
                    'carton_no_from' => $froms === [] ? null : min($froms),
                    'carton_no_to'   => $tos === [] ? null : max($tos),
                    'nw_per_carton'  => $firstRow['nw_per_carton'] ?? null,
                    'ttl_nw'         => $firstRow['ttl_nw'] ?? null,
                    'gw_per_carton'  => $firstRow['gw_per_carton'] ?? null,
                    'ttl_gw'         => $firstRow['ttl_gw'] ?? null,
                    'measurement_cm' => $firstRow['measurement_cm'] ?? null,
                    'cbm'            => $firstRow['cbm'] ?? null,
                ];

                if (count($distinctJans) >= 2) {
                    $items[] = [
                        'source_row'              => $groupKey,
                        'country'                 => $header['country'],
                        'buyer'                   => $header['buyer'],
                        'ocf'                     => $header['ocf'],
                        'style'                   => $header['style'],
                        'carton_no_from'          => $itemBase['carton_no_from'],
                        'carton_no_to'            => $itemBase['carton_no_to'],
                        'color'                   => implode(' / ', $colors),
                        'size'                    => count($sizeNames) === 1
                            ? $sizeNames[0]
                            : (count($sizeNames) > 1 ? implode('/', $sizeNames) : 'MIX'),
                        'qty_per_carton'          => $totalPcsPerCarton,
                        'jan_code'                => implode('-', $distinctJans),
                        'packing_list_qty_carton' => $cartonQty,
                        'receive_qty_carton'      => 0,
                        'balance'                 => $cartonQty,
                        'total_qty_pcs'           => $cartonQty * $totalPcsPerCarton,
                        'nw_per_carton'           => $itemBase['nw_per_carton'],
                        'ttl_nw'                  => $itemBase['ttl_nw'],
                        'gw_per_carton'           => $itemBase['gw_per_carton'],
                        'ttl_gw'                  => $itemBase['ttl_gw'],
                        'measurement_cm'          => $itemBase['measurement_cm'],
                        'cbm'                     => $itemBase['cbm'],
                        'mixed_jancode'           => true,
                    ];

                    continue;
                }

                // ASSORT: satu jancode, beberapa warna dalam karton yang sama.
                // Diimport sebagai SATU baris; warna digabung dengan ";".
                if (count($distinctJans) === 1 && ($isAssortByKet || count($colors) > 1)) {
                    $items[] = $this->assortItem(
                        $header,
                        $distinctJans[0],
                        $colors,
                        $sizeNames,
                        (int) $cartonQty,
                        $totalPcsPerCarton,
                        $groupKey,
                        $itemBase
                    );

                    continue;
                }

                $reasonCode = $isAssortByKet ? 'ASSORT' : 'MERGED_CARTON';
                $reasonLabel = $isAssortByKet
                    ? 'ASSORT PACK: Multiple size/color dalam satu karton gabungan'
                    : 'Broken: Qty karton di-merge (carton berisi multiple item/color/size)';

                $skipped[] = [
                    'source_row'    => $groupKey,
                    'reason'        => $reasonCode,
                    'reason_label'  => $reasonLabel,
                    'jan_code'      => implode(', ', $jans),
                    'color'         => implode(', ', $colors),
                    'size'          => implode(', ', $sizes),
                    'keterangan'    => $keterangan ?: 'MERGED',
                    'total_carton'  => $cartonQty,
                    'carton_merged' => true,
                    'raw_data'      => $subRowsDetails,
                    'notes'         => count($subRowsDetails) . " baris digabungkan dalam 1 karton (qty: {$cartonQty})",
                ];
                continue;
            }

            // ASSORT 1 baris: satu jancode, multi size/color dalam satu karton.
            // Tetap diimport sebagai 1 baris (qty/pcs digabung).
            if ($isAssortByKet) {
                $assortCarton = (int) ($r['total_carton'] ?? 0);
                $assortPcs = (int) array_sum($r['sizes_filled']);

                if (strlen($cleanJan) === 13 && $assortCarton > 0 && $assortPcs > 0) {
                    $items[] = $this->assortItem(
                        $header,
                        $cleanJan,
                        [$r['color_inherited']],
                        array_keys($r['sizes_filled']),
                        $assortCarton,
                        $assortPcs,
                        $rowNum,
                        $r
                    );

                    continue;
                }

                $skipped[] = [
                    'source_row'    => (string)$rowNum,
                    'reason'        => 'ASSORT',
                    'reason_label'  => 'Baris ASSORT PACK tidak valid (JAN/carton kosong)',
                    'jan_code'      => $cleanJan ?: $jan,
                    'color'         => $r['color_inherited'],
                    'size'          => implode(', ', array_map(fn($k, $v) => "$k ($v pcs)", array_keys($r['sizes_filled']), $r['sizes_filled'])),
                    'keterangan'    => $keterangan,
                    'total_carton'  => $r['total_carton'],
                    'carton_merged' => (bool)$r['carton_merged'],
                    'raw_data'      => $r,
                    'notes'         => 'Bukan tipe SOLID tunggal',
                ];
                continue;
            }

            // Jika kolom KETERANGAN ada dan bernilai selain SOLID
            if ($hasKeteranganCol && $keterangan !== '' && $keterangan !== 'SOLID') {
                $skipped[] = [
                    'source_row'    => (string)$rowNum,
                    'reason'        => 'OTHER',
                    'reason_label'  => 'Keterangan bukan SOLID (' . $keterangan . ')',
                    'jan_code'      => $cleanJan ?: $jan,
                    'color'         => $r['color_inherited'],
                    'size'          => implode(', ', array_map(fn($k, $v) => "$k ($v pcs)", array_keys($r['sizes_filled']), $r['sizes_filled'])),
                    'keterangan'    => $keterangan,
                    'total_carton'  => $r['total_carton'],
                    'carton_merged' => (bool)$r['carton_merged'],
                    'raw_data'      => $r,
                    'notes'         => "Keterangan sel: {$keterangan}",
                ];
                continue;
            }

            // R2: Carton tidak merge (tunggal)
            if ($r['carton_merged']) {
                $skipped[] = [
                    'source_row'    => (string)$rowNum,
                    'reason'        => 'MERGED_CARTON',
                    'reason_label'  => 'Broken: Kolom qty carton di-merge',
                    'jan_code'      => $cleanJan ?: $jan,
                    'color'         => $r['color_inherited'],
                    'size'          => implode(', ', array_map(fn($k, $v) => "$k ($v pcs)", array_keys($r['sizes_filled']), $r['sizes_filled'])),
                    'keterangan'    => $keterangan ?: 'MERGED',
                    'total_carton'  => $r['total_carton'],
                    'carton_merged' => true,
                    'raw_data'      => $r,
                    'notes'         => 'Sel carton berada di merge range',
                ];
                continue;
            }

            // R3: Punya JAN 13 digit
            if (strlen($cleanJan) !== 13) {
                $skipped[] = [
                    'source_row'    => (string)$rowNum,
                    'reason'        => 'NO_JAN',
                    'reason_label'  => 'JAN Code tidak valid (bukan 13 digit)',
                    'jan_code'      => $cleanJan ?: ($jan ?: '-'),
                    'color'         => $r['color_inherited'],
                    'size'          => implode(', ', array_map(fn($k, $v) => "$k ($v pcs)", array_keys($r['sizes_filled']), $r['sizes_filled'])),
                    'keterangan'    => $keterangan ?: '-',
                    'total_carton'  => $r['total_carton'],
                    'carton_merged' => false,
                    'raw_data'      => $r,
                    'notes'         => "Panjang JAN: " . strlen($cleanJan) . " digit",
                ];
                continue;
            }

            // R4: Validasi Size (Mampu mendeteksi ASSORT berdasarkan isi size tanpa kolom keterangan)
            $sizeCount = count($r['sizes_filled']);
            if ($sizeCount === 0) {
                $skipped[] = [
                    'source_row'    => (string)$rowNum,
                    'reason'        => 'NO_SIZE',
                    'reason_label'  => 'Tidak ada breakdown size yang terisi',
                    'jan_code'      => $cleanJan,
                    'color'         => $r['color_inherited'],
                    'size'          => '-',
                    'keterangan'    => $keterangan ?: '-',
                    'total_carton'  => $r['total_carton'],
                    'carton_merged' => false,
                    'raw_data'      => $r,
                    'notes'         => 'Kolom size kosong',
                ];
                continue;
            }

            // JIKA TERISI LEBIH DARI 1 SIZE DALAM 1 KARTON -> ASSORT 1 BARIS.
            // Diimport sebagai satu baris dengan size digabung "/".
            if ($sizeCount > 1) {
                $assortCarton = (int) ($r['total_carton'] ?? 0);
                $assortPcs = (int) array_sum($r['sizes_filled']);

                if ($assortCarton > 0 && $assortPcs > 0) {
                    $items[] = $this->assortItem(
                        $header,
                        $cleanJan,
                        [$r['color_inherited']],
                        array_keys($r['sizes_filled']),
                        $assortCarton,
                        $assortPcs,
                        $rowNum,
                        $r
                    );

                    continue;
                }

                $skipped[] = [
                    'source_row'    => (string)$rowNum,
                    'reason'        => 'ASSORT',
                    'reason_label'  => 'ASSORT PACK: Lebih dari satu size terisi dalam satu karton',
                    'jan_code'      => $cleanJan,
                    'color'         => $r['color_inherited'],
                    'size'          => implode(', ', array_map(fn($k, $v) => "$k ($v pcs)", array_keys($r['sizes_filled']), $r['sizes_filled'])),
                    'keterangan'    => $keterangan ?: 'ASSORT (Multi-size)',
                    'total_carton'  => $r['total_carton'],
                    'carton_merged' => false,
                    'raw_data'      => $r,
                    'notes'         => "Terisi {$sizeCount} size berbeda dalam satu baris karton",
                ];
                continue;
            }

            $sizeName = array_key_first($r['sizes_filled']);

            // R5: Punya total carton > 0
            $carton = $r['total_carton'];
            if (!$carton || $carton <= 0) {
                $skipped[] = [
                    'source_row'    => (string)$rowNum,
                    'reason'        => 'NO_CARTON',
                    'reason_label'  => 'Total carton kosong atau 0',
                    'jan_code'      => $cleanJan,
                    'color'         => $r['color_inherited'],
                    'size'          => $sizeName,
                    'keterangan'    => $keterangan ?: 'SOLID',
                    'total_carton'  => 0,
                    'carton_merged' => false,
                    'raw_data'      => $r,
                    'notes'         => 'Nilai carton kosong atau <= 0',
                ];
                continue;
            }

            // Normalisasi format Color
            $color = preg_replace('/\s+/', ' ', trim($r['color_inherited']));
            $pcsPerCarton = (int)($r['sizes_filled'][$sizeName] ?? 0);

            // Karton BROKEN: jancode yang sama muncul lebih dari sekali dan baris
            // ini qty pcs-nya di bawah ambang (mis. 30 pcs dari 60 pcs penuh).
            // Diberi akhiran "B" agar beda dari jancode normal dan tetap bisa discan.
            $itemJanCode = $cleanJan;
            $janStat = $standaloneJanStats[$cleanJan] ?? null;

            if (
                $janStat
                && $janStat['rows'] > 1
                && $pcsPerCarton > 0
                && $pcsPerCarton < $brokenThreshold
                && $pcsPerCarton < $janStat['max_pcs']
            ) {
                $itemJanCode = JancodeCode::markBroken($cleanJan);
            }

            // Valid Item (PASTI SOLID)
            $items[] = [
                'source_row'              => $rowNum,
                'country'                 => $header['country'],
                'buyer'                   => $header['buyer'],
                'ocf'                     => $header['ocf'],
                'style'                   => $header['style'],
                'carton_no_from'          => $r['carton_no_from'] ?? null,
                'carton_no_to'            => $r['carton_no_to'] ?? null,
                'color'                   => $color,
                'size'                    => $sizeName,
                'qty_per_carton'          => $pcsPerCarton,
                'jan_code'                => $itemJanCode,
                'packing_list_qty_carton' => $carton,
                'receive_qty_carton'      => 0,
                'balance'                 => $carton,
                'total_qty_pcs'           => $carton * $pcsPerCarton,
                'nw_per_carton'           => $r['nw_per_carton'] ?? null,
                'ttl_nw'                  => $r['ttl_nw'] ?? null,
                'gw_per_carton'           => $r['gw_per_carton'] ?? null,
                'ttl_gw'                  => $r['ttl_gw'] ?? null,
                'measurement_cm'          => $r['measurement_cm'] ?? null,
                'cbm'                     => $r['cbm'] ?? null,
                'broken_carton'           => $itemJanCode !== $cleanJan,
            ];
        }

        return [
            'header'  => $header,
            'items'   => $items,
            'skipped' => $skipped,
        ];
    }

    /**
     * Bentuk satu item ASSORT: satu jancode, beberapa warna/size dalam satu
     * karton. Warna digabung dengan ";", size digabung dengan "/".
     */
    private function assortItem(
        array $header,
        string $janCode,
        array $colors,
        array $sizeNames,
        int $cartonQty,
        int $pcsPerCarton,
        $sourceRow,
        array $base
    ): array {
        $colors = array_values(array_unique(array_filter(array_map(
            fn ($c) => preg_replace('/\s+/', ' ', trim((string) $c)),
            $colors
        ), fn ($c) => $c !== '')));

        $sizeNames = array_values(array_unique(array_filter(array_map(
            fn ($s) => trim((string) $s),
            $sizeNames
        ), fn ($s) => $s !== '')));

        $cartonQty = max(0, $cartonQty);
        $pcsPerCarton = max(0, $pcsPerCarton);

        return [
            'source_row'              => $sourceRow,
            'country'                 => $header['country'],
            'buyer'                   => $header['buyer'],
            'ocf'                     => $header['ocf'],
            'style'                   => $header['style'],
            'carton_no_from'          => $base['carton_no_from'] ?? null,
            'carton_no_to'            => $base['carton_no_to'] ?? null,
            'color'                   => implode('; ', $colors),
            'size'                    => count($sizeNames) === 1
                ? $sizeNames[0]
                : (count($sizeNames) > 1 ? implode('/', $sizeNames) : 'ASSORT'),
            'qty_per_carton'          => $pcsPerCarton,
            'jan_code'                => $janCode,
            'packing_list_qty_carton' => $cartonQty,
            'receive_qty_carton'      => 0,
            'balance'                 => $cartonQty,
            'total_qty_pcs'           => $cartonQty * $pcsPerCarton,
            'nw_per_carton'           => $base['nw_per_carton'] ?? null,
            'ttl_nw'                  => $base['ttl_nw'] ?? null,
            'gw_per_carton'           => $base['gw_per_carton'] ?? null,
            'ttl_gw'                  => $base['ttl_gw'] ?? null,
            'measurement_cm'          => $base['measurement_cm'] ?? null,
            'cbm'                     => $base['cbm'] ?? null,
            'assort'                  => true,
        ];
    }

    /**
     * Memanggil AI untuk ekstraksi JSON terstruktur jika dikonfigurasi.
     */
    private function callAiParser(array $header, string $structuredText): ?array
    {
        $geminiKey = config('services.gemini.api_key') ?: env('GEMINI_API_KEY');
        $openaiKey = env('OPENAI_API_KEY');

        $systemPrompt = <<<PROMPT
Kamu adalah parser packing list garment. Input adalah baris-baris sheet Excel dalam teks terstruktur.
Setiap baris punya: row (nomor baris Excel), cells (kolom -> nilai), carton_merged (true/false),
dan color_inherited (warna dari sel merge di atasnya).

TUGAS: ekstrak data master JAN code dengan aturan berikut.
1. Ambil dari header: buyer, style, country (label DESTINATION), ocf (label CPO).
   ocf = bagian angka sebelum " - " pada nilai CPO.
2. HANYA import baris SOLID (satu JAN, satu size tunggal, bukan assort). Abaikan baris ASSORT PACK.
3. JANGAN import baris dengan carton_merged = true (carton dipakai bersama beberapa baris).
4. JANGAN import baris TOTAL, QTY, TOTAL ASSORT PACK, TOTAL SOLID ALL COLOUR, GRAND TOTAL.
5. Baris valid harus punya JAN 13 digit, tepat satu size terisi (kolom breakdown size),
   dan TOTAL CARTON bilangan bulat > 0. Jika tidak, masukkan ke skipped.
6. size = nama size tunggal yang terisi (XS, S, M, L, XL, 2XL, dll.).
7. color = nilai color_inherited, trim spasi, huruf besar, format "KODE: NAMA".
8. packing_list_qty_carton = nilai TOTAL CARTON. Jangan menghitung ulang, jangan menebak.
9. Jangan mengarang data. Jika ragu pada sebuah baris, masukkan ke skipped dengan alasan.
10. Keluarkan HANYA JSON sesuai schema, tanpa teks lain.

Schema output:
{
  "header": { "buyer": "string", "style": "string", "country": "string", "ocf": "string" },
  "items": [
    { "source_row": 29, "jan_code": "4550725164908", "color": "000: WHITE", "size": "S", "packing_list_qty_carton": 3 }
  ],
  "skipped": [
    { "source_row": 12, "reason": "ASSORT" }
  ]
}
PROMPT;

        if ($geminiKey) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$geminiKey}";
            $response = Http::timeout(30)->post($url, [
                'system_instruction' => [
                    'parts' => [['text' => $systemPrompt]]
                ],
                'contents' => [
                    ['parts' => [['text' => "DATA EXCEL:\n" . $structuredText]]]
                ],
                'generationConfig' => [
                    'temperature'       => 0.0,
                    'response_mime_type'=> 'application/json'
                ]
            ]);

            if ($response->successful()) {
                $jsonText = $response->json('candidates.0.content.parts.0.text');
                return json_decode($jsonText, true);
            }
        } elseif ($openaiKey) {
            $response = Http::withToken($openaiKey)->timeout(30)->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => "DATA EXCEL:\n" . $structuredText]
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.0,
            ]);

            if ($response->successful()) {
                $content = $response->json('choices.0.message.content');
                return json_decode($content, true);
            }
        }

        return null;
    }

    /**
     * Memvalidasi hasil AI dengan aturan deterministik.
     */
    private function validateAiResult(array $aiResult, array $deterministicResult, array $header): array
    {
        // Petakan baris sumber valid dari deterministik
        $validRowMap = [];
        foreach ($deterministicResult['items'] as $item) {
            $validRowMap[$item['source_row']] = $item;
        }

        $items = [];
        $skipped = $deterministicResult['skipped'];

        if (!empty($aiResult['items']) && is_array($aiResult['items'])) {
            foreach ($aiResult['items'] as $aiItem) {
                $rowNum = $aiItem['source_row'] ?? null;
                if ($rowNum && isset($validRowMap[$rowNum])) {
                    $matched = $validRowMap[$rowNum];
                    $packingCarton = (int)($aiItem['packing_list_qty_carton'] ?? $matched['packing_list_qty_carton']);
                    $pcsPerCarton = (int)($matched['qty_per_carton'] ?? 0);
                    $items[] = [
                        'source_row'              => $rowNum,
                        'country'                 => $aiResult['header']['country'] ?? $header['country'],
                        'buyer'                   => $aiResult['header']['buyer'] ?? $header['buyer'],
                        'ocf'                     => $aiResult['header']['ocf'] ?? $header['ocf'],
                        'style'                   => $aiResult['header']['style'] ?? $header['style'],
                        'carton_no_from'          => $matched['carton_no_from'] ?? null,
                        'carton_no_to'            => $matched['carton_no_to'] ?? null,
                        'color'                   => $aiItem['color'] ?? $matched['color'],
                        'size'                    => $aiItem['size'] ?? $matched['size'],
                        'qty_per_carton'          => $pcsPerCarton,
                        'jan_code'                => $aiItem['jan_code'] ?? $matched['jan_code'],
                        'packing_list_qty_carton' => $packingCarton,
                        'receive_qty_carton'      => 0,
                        'balance'                 => $packingCarton,
                        'total_qty_pcs'           => $packingCarton * $pcsPerCarton,
                        'nw_per_carton'           => $matched['nw_per_carton'] ?? null,
                        'ttl_nw'                  => $matched['ttl_nw'] ?? null,
                        'gw_per_carton'           => $matched['gw_per_carton'] ?? null,
                        'ttl_gw'                  => $matched['ttl_gw'] ?? null,
                        'measurement_cm'          => $matched['measurement_cm'] ?? null,
                        'cbm'                     => $matched['cbm'] ?? null,
                        'assort'                  => ! empty($matched['assort']),
                        'mixed_jancode'           => ! empty($matched['mixed_jancode']),
                        'broken_carton'           => ! empty($matched['broken_carton']),
                    ];
                }
            }
        }

        // Jika AI melewatkan baris valid, fallback gunakan data deterministik
        if (count($items) === 0) {
            return $deterministicResult;
        }

        // Kasus khusus yang tidak boleh hilang walau AI tidak mengembalikannya:
        // - mix jancode / assort bisa punya source_row berupa rentang ("12-13");
        // - baris assort 1 baris (multi-size) sering dianggap "skip" oleh AI.
        $usedRows = [];
        foreach ($items as $it) {
            $usedRows[(string) ($it['source_row'] ?? '')] = true;
        }

        foreach ($deterministicResult['items'] as $detItem) {
            $rowKey = (string) ($detItem['source_row'] ?? '');
            $mustKeep = ! is_numeric($rowKey)
                || ! empty($detItem['assort'])
                || ! empty($detItem['mixed_jancode']);

            if ($mustKeep && ! isset($usedRows[$rowKey])) {
                $items[] = $detItem;
                $usedRows[$rowKey] = true;
            }
        }

        return [
            'header'  => $header,
            'items'   => $items,
            'skipped' => $skipped,
        ];
    }
}
