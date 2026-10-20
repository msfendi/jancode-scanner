<?php

namespace App\Exports\JancodeSheet;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Report packing list yang sudah FINISHED.
 *
 * Data diambil dari snapshot saat packing list di-Finish, sehingga report
 * selalu menampilkan kondisi "balance 0 / lengkap" meskipun setelahnya ada
 * carton out.
 */
class PackingListReportExport implements FromArray, WithStyles, WithColumnWidths, WithTitle, WithStrictNullComparison
{
    private const HEADER_ROW = 6;

    protected array $snapshot;

    public function __construct(array $snapshot)
    {
        $this->snapshot = $snapshot;
    }

    /**
     * Perbandingan ketat supaya nilai 0 (balance = 0) tetap ditulis,
     * bukan dianggap sama dengan null.
     */
    public function strictNullComparison(): bool
    {
        return true;
    }

    public function title(): string
    {
        return 'Finished Packing List';
    }

    public function array(): array
    {
        $packingList = $this->snapshot['packing_list'] ?? [];
        $revision    = $this->snapshot['revision'] ?? [];
        $summary     = $this->snapshot['summary'] ?? [];
        $rows        = $this->snapshot['rows'] ?? [];

        $data = [];

        $data[] = ['REPORT PACKING LIST FINISHED'];
        $data[] = [$packingList['label'] ?? '-'];
        $data[] = [
            'Session', $this->snapshot['session']['code'] ?? '-',
            'Revision', $revision['label'] ?? '-',
            'Tipe', $revision['doc_type'] ?? '-',
            'Batch', $revision['batch_number'] ?? '-',
        ];
        $data[] = [
            'Finished At', $this->snapshot['finished_at'] ?? '-',
            'Finished By', $this->snapshot['finished_by'] ?? '-',
            'Status', 'LENGKAP - BALANCE 0',
        ];
        $data[] = [
            'Total JAN', (int) ($summary['row_count'] ?? 0),
            'Total Carton', (int) ($summary['total_carton'] ?? 0),
            'Received', (int) ($summary['received_carton'] ?? 0),
            'Balance', (int) ($summary['balance_carton'] ?? 0),
            'Total Pcs', (int) ($summary['total_pcs'] ?? 0),
        ];

        $data[] = [
            'No', 'JAN Code', 'Color', 'Size', 'Carton No', 'Qty/Ctn',
            'PL Carton', 'Receive', 'Balance', 'Total Pcs', 'Status',
        ];

        $totalCarton = 0;
        $totalReceive = 0;
        $totalBalance = 0;
        $totalPcs     = 0;

        foreach ($rows as $row) {
            $totalCarton += (int) ($row['packing_list_qty_carton'] ?? 0);
            $totalReceive += (int) ($row['receive_qty_carton'] ?? 0);
            $totalBalance += (int) ($row['balance'] ?? 0);
            $totalPcs     += (int) ($row['total_qty_pcs'] ?? 0);

            $data[] = [
                (int) ($row['no'] ?? 0),
                $row['jancode'] ?? '-',
                $row['color'] ?? '-',
                $row['size'] ?? '-',
                $row['carton_no'] ?? '-',
                (int) ($row['qty_per_carton'] ?? 0),
                (int) ($row['packing_list_qty_carton'] ?? 0),
                (int) ($row['receive_qty_carton'] ?? 0),
                (int) ($row['balance'] ?? 0),
                (int) ($row['total_qty_pcs'] ?? 0),
                $row['status'] ?? 'Lengkap',
            ];
        }

        $data[] = [
            'TOTAL', '', '', '', '',
            '', $totalCarton, $totalReceive, $totalBalance, $totalPcs, 'Lengkap',
        ];

        return $data;
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = count($this->array());

        $border = [
            'border' => [
                'top'    => ['borderStyle' => Border::BORDER_THIN],
                'bottom' => ['borderStyle' => Border::BORDER_THIN],
                'left'   => ['borderStyle' => Border::BORDER_THIN],
                'right'  => ['borderStyle' => Border::BORDER_THIN],
            ],
        ];

        return [
            1 => [
                'font' => ['bold' => true, 'size' => 14, 'color' => ['argb' => 'FF1F3864']],
            ],
            2 => [
                'font' => ['bold' => true, 'size' => 12, 'color' => ['argb' => 'FF1F3864']],
            ],
            self::HEADER_ROW => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF4E73DF'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical'   => Alignment::HORIZONTAL_CENTER,
                    'wrapText'   => true,
                ],
                'borders' => $border['border'],
            ],
            $lastRow => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFE8F1FB'],
                ],
                'borders' => $border['border'],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,
            'B' => 18,
            'C' => 14,
            'D' => 8,
            'E' => 14,
            'F' => 9,
            'G' => 12,
            'H' => 11,
            'I' => 11,
            'J' => 13,
            'K' => 13,
        ];
    }
}
