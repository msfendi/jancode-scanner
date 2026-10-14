<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class JancodeImportService
{
    /**
     * Parse packing list excel file and return structured preview.
     */
    public function parseFile(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        // Out of scope: Sheet kedua diabaikan, hanya ambil sheet pertama
        $sheet = $spreadsheet->getSheet(0);

        // 1. Ekstrak Header
        $header = $this->extractHeader($sheet);

        // 2. Pre-processing: Deteksi merge cells & forward fill
        $preprocessed = $this->preprocessRows($sheet);

        // 3. Ekstraksi deterministik (Ground Truth Engine)
        $deterministicResult = $this->deterministicExtract($header, $preprocessed['rows']);

        // 4. AI Calling (Opsional / AI-Assisted jika API key tersedia)
        $finalResult = $deterministicResult;
        if (config('services.gemini.api_key') || env('GEMINI_API_KEY') || env('OPENAI_API_KEY')) {
            try {
                $aiResult = $this->callAiParser($header, $preprocessed['structured_text']);
                if ($aiResult && !empty($aiResult['items'])) {
                    // Validasi hasil AI terhadap aturan deterministik
                    $finalResult = $this->validateAiResult($aiResult, $deterministicResult, $header);
                }
            } catch (\Throwable $e) {
                Log::warning('AI parsing error, falling back to deterministic: ' . $e->getMessage());
                $finalResult = $deterministicResult;
            }
        }

        // Hitung total carton
        $totalCarton = array_sum(array_column($finalResult['items'], 'packing_list_qty_carton'));
        $deterministicCarton = array_sum(array_column($deterministicResult['items'], 'packing_list_qty_carton'));

        return [
            'header'            => $header,
            'items'             => $finalResult['items'],
            'skipped'           => $finalResult['skipped'],
            'total_items'       => count($finalResult['items']),
            'total_carton'      => $totalCarton,
            'skipped_count'     => count($finalResult['skipped']),
            'carton_match'      => ($totalCarton === $deterministicCarton),
            'expected_carton'   => $deterministicCarton,
        ];
    }

    /**
     * Ekstrak Header: BUYER, STYLE, DESTINATION, CPO.
     */
    private function extractHeader($sheet): array
    {
        $highestRow = min(15, $sheet->getHighestRow());
        $buyer = '';
        $style = '';
        $country = '';
        $ocf = '';

        for ($row = 1; $row <= $highestRow; $row++) {
            $label = strtoupper(trim((string)$sheet->getCell("A{$row}")->getValue()));
            $val = trim((string)$sheet->getCell("D{$row}")->getCalculatedValue());

            if (str_contains($label, 'BUYER')) {
                $buyer = $val;
            } elseif (str_contains($label, 'STYLE')) {
                $style = $val;
            } elseif (str_contains($label, 'DESTINATION')) {
                $country = $val;
            } elseif (str_contains($label, 'CPO')) {
                // Ambil angka sebelum " - " (contoh: 26139 - 266C0127-B5 -> 26139)
                $parts = explode('-', $val);
                $ocf = trim($parts[0] ?? $val);
            }
        }

        // Fallback default jika posisi tepat di D5..D8
        if (!$buyer) $buyer = trim((string)$sheet->getCell('D5')->getCalculatedValue());
        if (!$style) $style = trim((string)$sheet->getCell('D6')->getCalculatedValue());
        if (!$country) $country = trim((string)$sheet->getCell('D7')->getCalculatedValue());
        if (!$ocf) {
            $cpoVal = trim((string)$sheet->getCell('D8')->getCalculatedValue());
            $parts = explode('-', $cpoVal);
            $ocf = trim($parts[0] ?? $cpoVal);
        }

        return [
            'buyer'   => $buyer ?: 'UNKNOWN',
            'style'   => $style ?: 'UNKNOWN',
            'country' => $country ?: 'UNKNOWN',
            'ocf'     => $ocf ?: 'UNKNOWN',
        ];
    }

    /**
     * Membaca merge cell dan menyusun baris terstruktur dengan forward fill color.
     */
    private function preprocessRows($sheet): array
    {
        $mergeRanges = $sheet->getMergeCells();
        $mergedRowMap = [];   // [col][row] => is_multi_row_merged
        $mergedValueMap = []; // [col][row] => cell_value_from_top_left
        $cartonGroupMap = []; // [row] => "startRow-endRow"

        foreach ($mergeRanges as $range) {
            [$start, $end] = Coordinate::splitRange($range)[0];
            $startCol = preg_replace('/[0-9]/', '', $start);
            $startRow = (int) preg_replace('/[^0-9]/', '', $start);
            $endCol = preg_replace('/[0-9]/', '', $end);
            $endRow = (int) preg_replace('/[^0-9]/', '', $end);

            $topVal = trim((string)$sheet->getCell("{$startCol}{$startRow}")->getCalculatedValue());
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
                        if (in_array($colLetter, ['A', 'B', 'C', 'N'])) {
                            $cartonGroupMap[$r] = "{$startRow}-{$endRow}";
                        }
                    }
                }
            }
        }

        // Baca size headers (G11:L11)
        $sizeHeaders = [];
        for ($col = 'G'; $col <= 'L'; $col++) {
            $val = strtoupper(trim((string)$sheet->getCell("{$col}11")->getCalculatedValue()));
            if ($val) {
                $sizeHeaders[$col] = $val;
            }
        }

        $highestRow = $sheet->getHighestRow();
        $rows = [];
        $currentColor = '';
        $currentKeterangan = '';
        $structuredTextLines = [];

        for ($row = 12; $row <= $highestRow; $row++) {
            // 1. Resolve COLOR (Kolom D) dengan Merge Map + Forward Fill
            $rawColor = $mergedValueMap['D'][$row] ?? trim((string)$sheet->getCell("D{$row}")->getCalculatedValue());
            if (!empty($rawColor)) {
                $upperColor = strtoupper(trim($rawColor));
                // Abaikan jika sel D berisi baris ringkasan/total
                if (!str_contains($upperColor, 'TOTAL') && !str_contains($upperColor, 'GRAND') && !str_contains($upperColor, 'QTY')) {
                    $currentColor = $upperColor;
                }
            }

            // 2. Resolve KETERANGAN (Kolom F) dengan Merge Map + Forward Fill
            $rawKeterangan = $mergedValueMap['F'][$row] ?? trim((string)$sheet->getCell("F{$row}")->getCalculatedValue());
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

            // Flag carton_merged: true jika sel A, B, C, atau N berada dalam merged range multi-baris
            $cartonMerged = !empty($mergedRowMap['A'][$row]) ||
                            !empty($mergedRowMap['B'][$row]) ||
                            !empty($mergedRowMap['C'][$row]) ||
                            !empty($mergedRowMap['N'][$row]);

            $janCode = trim((string)$sheet->getCell("E{$row}")->getCalculatedValue());
            if (empty($janCode) && !empty($mergedValueMap['E'][$row])) {
                $janCode = trim($mergedValueMap['E'][$row]);
            }

            $totalCartonVal = $sheet->getCell("N{$row}")->getCalculatedValue();
            if ($totalCartonVal === null && !empty($mergedValueMap['N'][$row])) {
                $totalCartonVal = $mergedValueMap['N'][$row];
            }

            // Kumpulkan cells breakdown size
            $sizesFilled = [];
            foreach ($sizeHeaders as $col => $sizeName) {
                $val = (int) $sheet->getCell("{$col}{$row}")->getCalculatedValue();
                if ($val > 0) {
                    $sizesFilled[$sizeName] = $val;
                }
            }

            // Simpan data mentah baris
            $rowData = [
                'row'             => $row,
                'carton_merged'   => $cartonMerged,
                'carton_group'    => $cartonGroupMap[$row] ?? null,
                'color_inherited' => $currentColor,
                'jan_code'        => $janCode,
                'keterangan'      => $currentKeterangan,
                'sizes_filled'    => $sizesFilled,
                'total_carton'    => is_numeric($totalCartonVal) ? (int)$totalCartonVal : null,
                'col_a'           => trim((string)$sheet->getCell("A{$row}")->getCalculatedValue()),
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
     */
    public function deterministicExtract(array $header, array $rows): array
    {
        $items = [];
        $skipped = [];
        $processedCartonGroups = [];

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
                str_contains($keterangan, 'TOTAL')
            ) {
                $skipped[] = [
                    'source_row'    => (string)$rowNum,
                    'reason'        => 'SUMMARY_ROW',
                    'reason_label'  => 'Baris ringkasan/total (TOTAL, QTY, GRAND TOTAL)',
                    'jan_code'      => $cleanJan ?: $jan,
                    'color'         => $r['color_inherited'],
                    'size'          => '-',
                    'keterangan'    => $keterangan,
                    'total_carton'  => $r['total_carton'],
                    'carton_merged' => (bool)$r['carton_merged'],
                    'raw_data'      => $r,
                    'notes'         => 'Diabaikan karena baris total',
                ];
                continue;
            }

            // PENTING: Deteksi Karton Gabungan / Merge Qty / Assort yang memakai satu carton
            // "yang gagal seperti assort atau broken yang dia ada merge qty better diinput di table untuk failed input,
            // tapi berarti kolom jancode/color/size nya diisi dua data yang berbeda, bisa pakai koma bisa pakai json,
            // dianggap jadi satu row data dalam satu qty."
            if ($groupKey && ($r['carton_merged'] || $keterangan === 'ASSORT PACK' || str_contains($keterangan, 'ASSORT'))) {
                if (isset($processedCartonGroups[$groupKey])) {
                    // Sudah diproses bersama baris sebelumnya dalam merge group yang sama
                    continue;
                }
                $processedCartonGroups[$groupKey] = true;

                // Kumpulkan seluruh sub-baris dalam merge carton block ini
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

                    if ($cartonQty === null && $gr['total_carton'] !== null) {
                        $cartonQty = (int)$gr['total_carton'];
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
                $cartonQty = $cartonQty ?: 1; // Default 1 karton jika cell merge
                $isAssort = ($keterangan === 'ASSORT PACK' || str_contains($keterangan, 'ASSORT'));
                $reasonCode = $isAssort ? 'ASSORT' : 'MERGED_CARTON';
                $reasonLabel = $isAssort
                    ? 'ASSORT PACK: Multiple size/color dalam satu karton gabungan'
                    : 'Broken: Qty karton di-merge (carton berisi multiple item/color/size)';

                $skipped[] = [
                    'source_row'    => $groupKey,
                    'reason'        => $reasonCode,
                    'reason_label'  => $reasonLabel,
                    'jan_code'      => implode(', ', $jans),
                    'color'         => implode(', ', $colors),
                    'size'          => implode(', ', $sizes),
                    'keterangan'    => $keterangan,
                    'total_carton'  => $cartonQty,
                    'carton_merged' => true,
                    'raw_data'      => $subRowsDetails,
                    'notes'         => count($subRowsDetails) . " baris digabungkan dalam 1 karton (qty: {$cartonQty})",
                ];
                continue;
            }

            // R1: Hanya SOLID
            if ($keterangan === 'ASSORT PACK' || str_contains($keterangan, 'ASSORT')) {
                $skipped[] = [
                    'source_row'    => (string)$rowNum,
                    'reason'        => 'ASSORT',
                    'reason_label'  => 'Baris ASSORT PACK, tidak diimport',
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

            if ($keterangan !== 'SOLID') {
                $skipped[] = [
                    'source_row'    => (string)$rowNum,
                    'reason'        => 'OTHER',
                    'reason_label'  => 'Keterangan bukan SOLID',
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
                    'keterangan'    => $keterangan,
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
                    'keterangan'    => $keterangan,
                    'total_carton'  => $r['total_carton'],
                    'carton_merged' => false,
                    'raw_data'      => $r,
                    'notes'         => "Panjang JAN: " . strlen($cleanJan) . " digit",
                ];
                continue;
            }

            // R4: Tepat satu size terisi
            $sizeCount = count($r['sizes_filled']);
            if ($sizeCount === 0) {
                $skipped[] = [
                    'source_row'    => (string)$rowNum,
                    'reason'        => 'NO_SIZE',
                    'reason_label'  => 'Tidak ada breakdown size yang terisi',
                    'jan_code'      => $cleanJan,
                    'color'         => $r['color_inherited'],
                    'size'          => '-',
                    'keterangan'    => $keterangan,
                    'total_carton'  => $r['total_carton'],
                    'carton_merged' => false,
                    'raw_data'      => $r,
                    'notes'         => 'Kolom size G..L kosong',
                ];
                continue;
            }
            if ($sizeCount > 1) {
                $skipped[] = [
                    'source_row'    => (string)$rowNum,
                    'reason'        => 'MULTI_SIZE',
                    'reason_label'  => 'Lebih dari satu size terisi dalam satu baris',
                    'jan_code'      => $cleanJan,
                    'color'         => $r['color_inherited'],
                    'size'          => implode(', ', array_map(fn($k, $v) => "$k ($v pcs)", array_keys($r['sizes_filled']), $r['sizes_filled'])),
                    'keterangan'    => $keterangan,
                    'total_carton'  => $r['total_carton'],
                    'carton_merged' => false,
                    'raw_data'      => $r,
                    'notes'         => "Terisi {$sizeCount} size berbeda",
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
                    'keterangan'    => $keterangan,
                    'total_carton'  => 0,
                    'carton_merged' => false,
                    'raw_data'      => $r,
                    'notes'         => 'Nilai kolom N kosong atau <= 0',
                ];
                continue;
            }

            // Normalisasi format Color
            $color = preg_replace('/\s+/', ' ', trim($r['color_inherited']));
            $pcsPerCarton = (int)($r['sizes_filled'][$sizeName] ?? 0);

            // Valid Item
            $items[] = [
                'source_row'              => $rowNum,
                'country'                 => $header['country'],
                'buyer'                   => $header['buyer'],
                'ocf'                     => $header['ocf'],
                'style'                   => $header['style'],
                'color'                   => $color,
                'size'                    => $sizeName,
                'qty_per_carton'          => $pcsPerCarton,
                'jan_code'                => $cleanJan,
                'packing_list_qty_carton' => $carton,
                'receive_qty_carton'      => 0,
                'balance'                 => $carton,
                'total_qty_pcs'           => $carton * $pcsPerCarton,
            ];
        }

        return [
            'header'  => $header,
            'items'   => $items,
            'skipped' => $skipped,
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
2. HANYA import baris dengan KETERANGAN = "SOLID". Abaikan semua baris "ASSORT PACK".
3. JANGAN import baris dengan carton_merged = true (carton dipakai bersama beberapa baris).
4. JANGAN import baris TOTAL, QTY, TOTAL ASSORT PACK, TOTAL SOLID ALL COLOUR, GRAND TOTAL.
5. Baris valid harus punya JAN 13 digit, tepat satu size terisi (kolom breakdown size),
   dan TOTAL CARTON bilangan bulat > 0. Jika tidak, masukkan ke skipped.
6. size = judul kolom size yang terisi (XS, S, M, L, XL, 2XL).
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
                        'color'                   => $aiItem['color'] ?? $matched['color'],
                        'size'                    => $aiItem['size'] ?? $matched['size'],
                        'qty_per_carton'          => $pcsPerCarton,
                        'jan_code'                => $aiItem['jan_code'] ?? $matched['jan_code'],
                        'packing_list_qty_carton' => $packingCarton,
                        'receive_qty_carton'      => 0,
                        'balance'                 => $packingCarton,
                        'total_qty_pcs'           => $packingCarton * $pcsPerCarton,
                    ];
                }
            }
        }

        // Jika AI melewatkan baris valid, fallback gunakan data deterministik
        if (count($items) === 0) {
            return $deterministicResult;
        }

        return [
            'header'  => $header,
            'items'   => $items,
            'skipped' => $skipped,
        ];
    }
}
