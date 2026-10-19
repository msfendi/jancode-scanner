<?php

namespace App\Console\Commands;

use App\Models\ImportBatch;
use App\Models\ImportFailedRow;
use App\Models\ImportSession;
use App\Models\Jancode;
use App\Models\JancodeLogs;
use App\Models\PackingList;
use App\Models\PalletAssignmentDetail;
use App\Models\ScanBatch;
use App\Services\JancodeImportService;
use App\Services\PackingListImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Import massal file packing list (PROFORMA / ACTUAL) untuk keperluan demo.
 *
 * Contoh:
 *   php artisan packing:import "C:/Users/lenovo/Downloads" --fresh
 */
class ImportPackingListCommand extends Command
{
    protected $signature = 'packing:import
        {paths* : File .xlsx/.xls atau folder yang berisi file packing list}
        {--fresh : Hapus seluruh data packing list, jancode, dan scan terlebih dahulu}';

    protected $description = 'Import packing list PROFORMA/ACTUAL (per sheet = 1 revisi) untuk demo';

    public function handle(JancodeImportService $parser, PackingListImportService $importer): int
    {
        if ($this->option('fresh')) {
            $this->warn('Menghapus data lama (jancode, import batche, scan)...');
            DB::statement('PRAGMA foreign_keys = OFF');
            JancodeLogs::query()->delete();
            ScanBatch::query()->delete();
            PalletAssignmentDetail::query()->delete();
            Jancode::query()->delete();
            ImportFailedRow::query()->delete();
            ImportBatch::query()->delete();
            PackingList::query()->delete();
            ImportSession::query()->delete();
            DB::statement('PRAGMA foreign_keys = ON');
        }

        $files = $this->collectFiles($this->argument('paths'));

        if (empty($files)) {
            $this->error('Tidak ada file .xlsx/.xls yang ditemukan.');
            return self::FAILURE;
        }

        $totalBatch = 0;
        $totalItems = 0;
        $totalBroken = 0;

        // Satu session per kombinasi style|OCF|destination|warehouse (group_key),
        // agar file dengan header sama tetap dianggap revisi, sedangkan group lain
        // mendapat session sendiri.
        $sessionByGroup = [];

        foreach ($files as $file) {
            $this->info('');
            $this->line('▶ ' . basename($file));

            try {
                $parsed = $parser->parseWorkbook($file);
            } catch (\Throwable $e) {
                $this->error('  Gagal parse: ' . $e->getMessage());
                continue;
            }

            if (empty($parsed['sheets'])) {
                $this->warn('  Tidak ada sheet packing list, dilewati.');
                continue;
            }

            foreach ($parsed['sheets'] as $sheet) {
                $groupKey = ImportBatch::buildGroupKey($sheet['header']);

                if (!isset($sessionByGroup[$groupKey])) {
                    $sessionByGroup[$groupKey] = ImportSession::create([
                        'code'         => ImportSession::generateCode(),
                        'notes'        => 'Import CLI: ' . basename($file),
                        'group_key'    => $groupKey,
                        'style_number' => $sheet['header']['style'] ?? null,
                        'ocf_number'   => $sheet['header']['ocf'] ?? null,
                        'destination'  => $sheet['header']['country'] ?? null,
                        'warehouse'    => $sheet['header']['warehouse'] ?? null,
                        'supplier_name'=> $sheet['header']['buyer'] ?? null,
                        'status'       => 'open',
                        'started_at'   => now(),
                    ]);
                }

                $result = DB::transaction(fn() => $importer->importSheet($sheet, [
                    'session_id'        => $sessionByGroup[$groupKey]->id,
                    'original_filename' => basename($file),
                    'imported_by'       => null,
                ]));

                if (!$result) {
                    continue;
                }

                $batch = $result['batch'];
                $totalBatch++;
                $totalItems  += $result['imported'];
                $totalBroken += $result['skipped'];

                $this->line(sprintf(
                    '   • sheet "%s" → %s Rev.%d | %d carton | valid %d | broken %d',
                    $sheet['sheet_name'],
                    strtoupper($batch->doc_type),
                    $batch->revision_no,
                    $batch->total_carton,
                    $result['imported'],
                    $result['skipped']
                ));
            }
        }

        $this->info('');
        $this->info("Selesai. {$totalBatch} packing list (revisi), {$totalItems} baris valid, {$totalBroken} baris broken.");

        $activeGroups = ImportBatch::active()->count();
        $this->info("Packing list aktif saat ini: {$activeGroups} group.");

        return self::SUCCESS;
    }

    private function collectFiles(array $paths): array
    {
        $files = [];

        foreach ($paths as $path) {
            if (is_dir($path)) {
                $found = glob(rtrim($path, '\\/') . DIRECTORY_SEPARATOR . '*.xls*') ?: [];
                $files = array_merge($files, $found);
            } elseif (is_file($path)) {
                $files[] = $path;
            }
        }

        $files = array_values(array_unique($files));
        sort($files);

        return array_values(array_filter($files, function ($f) {
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            // Hindari file sementara Excel (~$...)
            return in_array($ext, ['xlsx', 'xls']) && strpos(basename($f), '~$') !== 0;
        }));
    }
}
