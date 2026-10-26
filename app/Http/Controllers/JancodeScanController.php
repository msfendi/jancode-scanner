<?php

namespace App\Http\Controllers;

use App\Models\ImportBatch;
use App\Models\ImportSession;
use App\Models\Jancode;
use App\Models\JancodeLogs;
use App\Models\ScanBatch;
use App\Services\ScannerJoinService;
use App\Support\JancodeCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Scanner Jancode per PACKING LIST.
 *
 * Alur:
 * 1. Operator memilih packing list (revisi ACTIVE dari sebuah group).
 * 2. Sistem memuat seluruh jancode pada revisi aktif tersebut beserta balance.
 * 3. Setiap 1 scan = 1 carton. Barcode harus cocok dengan salah satu jancode
 *    pada packing list yang sedang aktif.
 * 4. Submit menyimpan scan sementara menjadi batch scan (BSCAN-...).
 */
class JancodeScanController extends Controller
{
    private const SESSION_KEY = 'scan_import_batch_id';

    // Halaman utama scanner
    public function index(Request $request, ScannerJoinService $joiner)
    {
        // ── Join dari perangkat lain (HP) lewat QR ──────────────────
        // memakai route yang sudah ada (/scanner?join=...) supaya tidak
        // bergantung pada route baru / route cache yang belum di-refresh.
        if ($request->filled('join')) {
            $this->applyJoinToken($request->query('join'), $joiner);

            return redirect(url('/scanner'));
        }

        $currentSession = ImportSession::current();

        // Mode monitor: device lain membuka session yang sama tanpa memilih packing list.
        // Otomatis muat packing list aktif terakhir pada session tersebut.
        $monitorBatchId = null;
        if ($request->boolean('monitor') && $currentSession && !$request->session()->has(self::SESSION_KEY)) {
            $monitorBatchId = ImportBatch::active()
                ->where('session_id', $currentSession->id)
                ->orderByDesc('id')
                ->value('id');
        }

        $lockedBatchId = session(self::SESSION_KEY);

        // QR Code join untuk perangkat lain (HP). Dirender di server supaya
        // halaman tidak bergantung pada route tambahan (aman dari route cache).
        $qr = null;

        if ($currentSession) {
            $batchId = $lockedBatchId ?: $monitorBatchId;
            $batch   = $batchId ? ImportBatch::find($batchId) : null;

            if ($batch && (int) $batch->session_id === (int) $currentSession->id) {
                $token = $joiner->encode((int) $currentSession->id, (int) $batch->id);
                $url   = url('/scanner?join=' . $token);

                $qr = [
                    'svg'           => $joiner->svg($url),
                    'url'           => $url,
                    'session_code'  => $currentSession->code,
                    'session_label' => $currentSession->group_label,
                    'batch_label'   => $batch->group_label,
                    'batch_number'  => $batch->batch_number,
                ];
            }
        }

        return view('jancode.scanner', [
            'currentSession' => $currentSession,
            'monitorBatchId' => $monitorBatchId,
            // Saat halaman dibuka ulang, muat kembali packing list yang masih
            // terkunci di session ini agar riwayat log scan langsung tampil.
            'lockedBatchId'  => $lockedBatchId,
            'qr'             => $qr,
        ]);
    }

    /**
     * Terapkan token join: set session aktif + kunci packing list tujuan.
     */
    private function applyJoinToken(?string $token, ScannerJoinService $joiner): bool
    {
        $data = $joiner->decode($token);

        if (! $data) {
            return false;
        }

        $session = ImportSession::find($data['s']);

        if (! $session) {
            return false;
        }

        // Pindah session aktif ke session tujuan.
        ImportSession::setCurrent((int) $session->id);

        // Kunci packing list yang sama (bila masih revisi aktif).
        if (! empty($data['b'])) {
            $batch = ImportBatch::find($data['b']);

            if ($batch
                && (int) $batch->session_id === (int) $session->id
                && $batch->is_active) {
                session([self::SESSION_KEY => (int) $batch->id]);
            }
        }

        return true;
    }

    /**
     * Daftar packing list aktif untuk Select2.
     */
    public function packingLists(Request $request)
    {
        $q = $request->input('q');

        $query = ImportBatch::active()
            ->where('session_id', ImportSession::currentId() ?? 0)
            ->orderByDesc('id');
        if ($q) {
            $query->where(function ($w) use ($q) {
                $w->where('group_label', 'LIKE', "%$q%")
                  ->orWhere('batch_number', 'LIKE', "%$q%")
                  ->orWhere('style_number', 'LIKE', "%$q%")
                  ->orWhere('ocf_number', 'LIKE', "%$q%")
                  ->orWhere('destination', 'LIKE', "%$q%")
                  ->orWhere('warehouse', 'LIKE', "%$q%");
            });
        }

        $results = $query->limit(30)->get()->map(function ($b) {
            return [
                'id'    => $b->id,
                'text'  => $b->summary_label,
                'label' => $b->group_label,
            ];
        });

        return response()->json($results);
    }

    /**
     * Muat packing list (revisi aktif) ke sesi scan.
     */
    public function load(Request $request)
    {
        $batchId = $request->input('import_batch_id');
        if (!$batchId) {
            return response()->json(['success' => false, 'message' => 'Packing list tidak boleh kosong'], 400);
        }

        $batch = ImportBatch::find($batchId);
        if (!$batch) {
            return response()->json(['success' => false, 'message' => 'Packing list tidak ditemukan'], 404);
        }

        $currentSession = ImportSession::current();
        if (!$currentSession) {
            return response()->json([
                'success' => false,
                'error'   => 'no_session',
                'message' => 'Mulai session terlebih dahulu sebelum scan.',
            ], 422);
        }

        if (!$currentSession->isOpen()) {
            return response()->json([
                'success' => false,
                'error'   => 'session_closed',
                'message' => 'Session ini sudah ditutup. Buat session baru untuk scan.',
            ], 422);
        }

        if ((int) $batch->session_id !== (int) $currentSession->id) {
            return response()->json([
                'success' => false,
                'error'   => 'wrong_session',
                'message' => 'Packing list ini bukan milik session aktif.',
            ], 422);
        }

        if (!$batch->is_active) {
            return response()->json([
                'success' => false,
                'error'   => 'not_active',
                'message' => 'Packing list ini bukan revisi aktif. Silakan pilih packing list terbaru.',
            ], 422);
        }

        if ($guard = $this->finishedGuard($batch)) {
            return response()->json($guard, 422);
        }

        $lockedId = session(self::SESSION_KEY);
        if ($lockedId && (int)$lockedId !== (int)$batchId) {
            $unsubmitted = $this->unsubmittedCount((int)$lockedId);
            if ($unsubmitted > 0) {
                return response()->json([
                    'success' => false,
                    'error'   => 'locked',
                    'message' => "Masih ada {$unsubmitted} scan sementara pada packing list sebelumnya. Submit atau rollback terlebih dahulu.",
                ], 403);
            }
        }

        session([self::SESSION_KEY => (int)$batchId]);

        return response()->json($this->batchState($batch) + [
            'success' => true,
            'message' => 'Packing list dimuat.',
        ]);
    }

    /**
     * Refresh data baris packing list.
     *
     * Dengan `?delta=1` response hanya berisi baris + counter (ringan),
     * dipakai untuk sinkronisasi live antar perangkat.
     */
    public function rows(Request $request)
    {
        $batch = $this->resolveSessionBatch();
        if (!$batch) {
            return response()->json(['success' => false, 'message' => 'Pilih packing list terlebih dahulu'], 400);
        }

        if ($request->boolean('delta')) {
            $state = $this->lightState($batch);

            return response()->json([
                'success'  => true,
                'hash'     => $state['hash'],
                'counters' => $state['counters'],
                'rows'     => array_values($state['rows']),
            ]);
        }

        return response()->json($this->batchState($batch) + ['success' => true]);
    }

    /**
     * QR Code untuk scan dari perangkat lain (HP).
     *
     * QR berisi link bertanda tangan yang memuat session aktif + packing list
     * yang sedang dimuat. Setelah user login di HP, link yang sama langsung
     * mengarahkan ke session scan yang sesuai.
     */
    public function qr(Request $request, ScannerJoinService $joiner)
    {
        $session = ImportSession::current();

        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'Belum ada session aktif. Pilih session terlebih dahulu.',
            ], 404);
        }

        $batch = $this->resolveSessionBatch();

        $token = $joiner->encode((int) $session->id, $batch?->id);
        $url   = url('/scanner/join', ['c' => $token]);

        return response()->json([
            'success'       => true,
            'svg'           => $joiner->svg($url),
            'url'           => $url,
            'session_code'  => $session->code,
            'session_label' => $session->group_label,
            'batch_label'   => $batch?->group_label,
            'batch_number'  => $batch?->batch_number,
            'has_batch'     => (bool) $batch,
        ]);
    }

    /**
     * Join dari perangkat lain (HP) via link QR.
     *
     * Set session aktif + kunci packing list yang sama dengan perangkat
     * desktop, lalu arahkan ke halaman scanner.
     */
    public function join(Request $request, ScannerJoinService $joiner)
    {
        $ok = $this->applyJoinToken($request->query('c') ?: $request->query('join'), $joiner);

        if (!$ok) {
            return redirect(url('/scanner'))
                ->with('error', 'QR Code tidak valid atau sudah kedaluwarsa. Silakan generate ulang di perangkat lain.');
        }

        return redirect(url('/scanner'))
            ->with('success', 'Bergabung ke session scan. Silakan lanjut scan.');
    }

    /**
     * Delta live update: state ringkas (baris + counter) untuk sinkronisasi
     * antar perangkat.
     *
     * DipakaiInstead SSE supaya request tidak menahan worker server (penting
     * untuk `php artisan serve` yang hanya single-thread): payload kecil dan
     * selesai cepat.
     */
    public function delta(Request $request)
    {
        $batch = $this->resolveSessionBatch();

        if (!$batch) {
            return response()->json([
                'success' => false,
                'message' => 'Pilih packing list terlebih dahulu.',
            ], 400);
        }

        $state = $this->lightState($batch);

        return response()->json([
            'success'  => true,
            'hash'     => $state['hash'],
            'counters' => $state['counters'],
            'rows'     => array_values($state['rows']),
        ]);
    }

    /**
     * SSE live monitor: kirim state baris setiap ada scan baru.
     *
     * Agarupdate terasa cepat, server hanya mengirim PERUBAHAN (delta):
     * baris yang berubah + counter, bukan seluruh tabel. Browser cukup
     * menambal baris yang terkait (tanpa rebuild tabel).
     */
    public function stream(Request $request): StreamedResponse
    {
        $batch = $this->resolveSessionBatch();
        if (!$batch) {
            return response()->json([
                'success' => false,
                'error'   => 'no_packing_list',
                'message' => 'Pilih packing list terlebih dahulu.',
            ], 400);
        }

        $lastHash = (string) $request->query('t', '');

        // max detik bisa diperkecil untuk pengujian; default 55 detik.
        $maxSeconds = min(55, max(1, (int) $request->query('max', 55)));

        // Lepas session lock supaya request lain (scan/submit) tidak ikut tertahan.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        return new StreamedResponse(function () use ($batch, $lastHash, $maxSeconds) {
            @set_time_limit(0);
            ignore_user_abort(true);
            if (ob_get_level() > 0) { @ob_end_clean(); }

            $prev     = $lastHash;
            $prevRows = [];
            $start    = time();

            $emit = function () use ($batch, &$prev, &$prevRows) {
                $snapshot = $this->lightState($batch);
                $hash     = $snapshot['hash'];

                if ($hash === $prev) {
                    return false;
                }

                // Baris yang berubah saja (delta).
                $changed = [];
                foreach ($snapshot['rows'] as $id => $row) {
                    $before = $prevRows[$id] ?? null;

                    if ($before === null
                        || $before['receive'] !== $row['receive']
                        || $before['unsubmitted'] !== $row['unsubmitted']) {
                        $changed[] = $row;
                    }
                }

                $prev     = $hash;
                $prevRows = $snapshot['rows'];

                $payload = [
                    'success'      => true,
                    'hash'         => $hash,
                    'counters'     => $snapshot['counters'],
                    'changed_rows' => $changed,
                ];

                echo "event: state\n";
                echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n\n";
                @ob_flush();
                @flush();

                return true;
            };

            $emit();

            while ((time() - $start) < $maxSeconds) {
                if (connection_aborted()) {
                    break;
                }
                usleep(400000); // 0.4 detik — scan dari device lain langsung tampil
                $emit();
                echo ': hb ' . time() . "\n\n";
                @ob_flush();
                @flush();
            }

            echo "event: done\ndata: closing\n\n";
            @ob_flush();
            @flush();
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache, no-transform',
            'Connection'        => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * State ringkas untuk live update: counter + status per baris.
     *
     * Dipakai SSE agar payload yang dikirim ke browser kecil (hanya perubahan),
     * sehingga update terasa cepat mesmo di HP.
     *
     * @return array{hash:string, rows:array<int, array>, counters:array}
     */
    private function lightState(ImportBatch $batch): array
    {
        // Satu query: state per baris + jumlah scan sementara (belum submit).
        $rows = Jancode::where('import_batch_id', $batch->id)
            ->orderBy('id')
            ->select(['id', 'packing_list_qty_carton', 'receive_qty_carton'])
            ->selectRaw(
                "(SELECT COUNT(*) FROM jancode_logs l
                  WHERE l.jancode_id = jancode.id AND l.is_submitted = 'false') as unsubmitted_count"
            )
            ->get();

        $state       = [];
        $signature   = '';
        $totalCarton = 0;
        $scanned     = 0;
        $unsubmitted = 0;
        $done        = 0;

        foreach ($rows as $row) {
            $packing = (int) $row->packing_list_qty_carton;
            $receive = (int) $row->receive_qty_carton;
            $unsub   = (int) ($row->unsubmitted_count ?? 0);
            $balance = max(0, $packing - $receive - $unsub);

            $state[$row->id] = [
                'id'          => (int) $row->id,
                'receive'     => $receive,
                'unsubmitted' => $unsub,
                'balance'     => $balance,
                'is_complete' => $balance === 0,
            ];

            $signature .= $row->id . ':' . $receive . ':' . $unsub . ';';

            $totalCarton += $packing;
            $scanned     += $receive;
            $unsubmitted += $unsub;

            if ($balance === 0) {
                $done++;
            }
        }

        $counters = [
            'scanned'     => $scanned,
            'unsubmitted' => $unsubmitted,
            'total'       => $totalCarton,
            'balance'     => max(0, $totalCarton - $scanned - $unsubmitted),
            'rows_total'  => $rows->count(),
            'rows_done'   => $done,
            'is_complete' => ($totalCarton > 0 && ($scanned + $unsubmitted) >= $totalCarton),
        ];

        return [
            'hash'     => md5($signature . '|' . json_encode($counters)),
            'rows'     => $state,
            'counters' => $counters,
        ];
    }

    // POST scan — 1 scan = 1 carton
    public function scan(Request $request)
    {
        // Normalisasi: huruf besar, "-" dipertahankan (case mix jancode),
        // akhiran "B" dipertahankan (karton broken).
        $barcode = JancodeCode::normalize((string) $request->input('jancode'));

        if ($barcode === '') {
            return response()->json(['success' => false, 'message' => 'Barcode tidak boleh kosong'], 400);
        }

        $batchId = session(self::SESSION_KEY);
        if (!$batchId) {
            return response()->json([
                'success' => false,
                'error'   => 'no_packing_list',
                'message' => 'Pilih packing list terlebih dahulu sebelum scan.',
            ], 400);
        }

        $batch = ImportBatch::find($batchId);
        if (!$batch || !$batch->is_active) {
            session()->forget(self::SESSION_KEY);
            return response()->json([
                'success' => false,
                'error'   => 'not_active',
                'message' => 'Packing list yang dipilih sudah tidak aktif (ada revisi baru). Silakan muat ulang.',
            ], 409);
        }

        $currentSession = ImportSession::current();
        if (!$currentSession || (int) $batch->session_id !== (int) $currentSession->id) {
            session()->forget(self::SESSION_KEY);
            return response()->json([
                'success' => false,
                'error'   => 'wrong_session',
                'message' => 'Packing list ini bukan milik session aktif. Silakan muat ulang.',
            ], 409);
        }

        if ($guard = $this->finishedGuard($batch)) {
            return response()->json($guard, 409);
        }

        if (!$currentSession->isOpen()) {
            return response()->json([
                'success' => false,
                'error'   => 'session_closed',
                'message' => 'Session ini sudah ditutup. Buat session baru untuk scan.',
            ], 422);
        }

        $count = max(1, (int)$request->input('count', 1));

        try {
            DB::beginTransaction();

            $master = $this->findMasterForBarcode($batchId, $barcode);

            if (!$master) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'error'   => 'not_found',
                    'message' => 'Barcode tidak ada pada packing list ini.',
                ], 404);
            }

            // Karton yang sudah "keluar" tidak dihitung, supaya slot bisa discan ulang.
            $totalAllCount = JancodeLogs::where('jancode_id', $master->id)->inStock()->count();
            $capacity = (int)$master->packing_list_qty_carton;

            if ($totalAllCount + $count > $capacity) {
                DB::rollBack();
                return response()->json([
                    'success'  => false,
                    'error'    => 'over',
                    'message'  => 'Qty karton untuk jancode ini sudah terpenuhi (sisa ' . max(0, $capacity - $totalAllCount) . ' karton).',
                    'counters' => $this->batchState($batch, false)['counters'],
                ], 422);
            }

            $logsToInsert = [];
            $now = now();
            for ($i = 0; $i < $count; $i++) {
                $logsToInsert[] = [
                    'jancode'         => $barcode,
                    'jancode_id'      => $master->id,
                    'packing_list_id' => $master->packing_list_id ?? $batch->packing_list_id,
                    'import_batch_id' => $batch->id,
                    'session_id'      => $batch->session_id,
                    'user_id'         => auth()->id(),
                    'is_submitted'    => 'false',
                    'status'          => JancodeLogs::STATUS_IN,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ];
            }
            JancodeLogs::insert($logsToInsert);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem saat menyimpan data',
                'error'   => $e->getMessage(),
            ], 500);
        }

        // Payload ringan: hanya header + counter + baris yang baru discan.
        $state = $this->batchState($batch, false);

        $unsubmittedForRow = JancodeLogs::where('jancode_id', $master->id)
            ->where('is_submitted', 'false')
            ->count();

        return response()->json($state + [
            'success'         => true,
            'scanned_barcode' => $barcode,
            'jancode_id'      => $master->id,
            'row'             => $this->buildRowArray($master, $unsubmittedForRow),
        ]);
    }

    // GET count — refresh counters
    public function count(Request $request)
    {
        $batch = $this->resolveSessionBatch();
        if (!$batch) {
            return response()->json(['scanned' => 0, 'unsubmitted' => 0, 'total_all' => 0, 'qty' => 0]);
        }

        return response()->json($this->batchState($batch));
    }

    /**
     * Submit seluruh scan sementara pada packing list menjadi 1 batch scan.
     */
    public function submit(Request $request)
    {
        $batchId = session(self::SESSION_KEY);
        if (!$batchId) {
            return response()->json(['success' => false, 'message' => 'Pilih packing list terlebih dahulu'], 400);
        }

        $batch = ImportBatch::find($batchId);
        if (!$batch) {
            return response()->json(['success' => false, 'message' => 'Packing list tidak ditemukan'], 404);
        }

        $currentSession = ImportSession::current();
        if (!$currentSession || (int) $batch->session_id !== (int) $currentSession->id) {
            return response()->json([
                'success' => false,
                'error'   => 'wrong_session',
                'message' => 'Packing list ini bukan milik session aktif.',
            ], 403);
        }

        if ($guard = $this->finishedGuard($batch)) {
            return response()->json($guard, 422);
        }

        // Partial scan sesuai kedatangan: setiap submit dicatat dengan kode
        // surat jalan / catatan agar bisa dilacak per kedatangan.
        $validated = $request->validate([
            'surat_jalan' => 'nullable|string|max:100',
            'notes'       => 'nullable|string|max:500',
        ]);

        $suratJalan = trim((string) ($validated['surat_jalan'] ?? ''));
        $userNotes  = trim((string) ($validated['notes'] ?? ''));

        try {
            DB::beginTransaction();

            $jancodes = Jancode::where('import_batch_id', $batchId)->where('void', 'false')->lockForUpdate()->get();
            $jancodeIds = $jancodes->pluck('id')->all();

            $unsubmittedCount = JancodeLogs::whereIn('jancode_id', $jancodeIds)
                ->where('is_submitted', 'false')
                ->count();

            if ($unsubmittedCount <= 0) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Tidak ada scan sementara untuk disubmit'], 400);
            }

            // Generate Batch Number unik: BSCAN-YYYYMMDD-XXXX
            $today  = date('Ymd');
            $prefix = "BSCAN-{$today}-";
            $lastBatch = ScanBatch::where('batch_no', 'like', "{$prefix}%")->orderByDesc('id')->first();
            $nextSeq = $lastBatch ? ((int)substr($lastBatch->batch_no, strlen($prefix)) + 1) : 1;
            $batchNo = $prefix . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);

            $totalCarton = (int)$jancodes->sum('packing_list_qty_carton');

            // Simpan batch scan (per packing list)
            $scanBatch = ScanBatch::create([
                'batch_no'        => $batchNo,
                'jancode_id'      => null,
                'import_batch_id' => $batchId,
                'packing_list_id' => $batch->packing_list_id,
                'session_id'      => $batch->session_id,
                'user_id'         => auth()->id(),
                'scanned_qty'     => $unsubmittedCount,
                'receive_qty'     => 0,
                'balance'         => 0,
                'total_qty'       => $totalCarton,
                'surat_jalan'     => $suratJalan !== '' ? $suratJalan : null,
                'notes'           => trim(
                    "Batch scan {$unsubmittedCount} karton pada {$batch->group_label} oleh "
                    . (auth()->user()?->name ?? 'Operator')
                    . ($userNotes !== '' ? " | Catatan: {$userNotes}" : '')
                ),
            ]);

            // Tandai log tersubmit
            JancodeLogs::whereIn('jancode_id', $jancodeIds)
                ->where('is_submitted', 'false')
                ->update([
                    'is_submitted'  => 'true',
                    'user_id'       => auth()->id(),
                    'scan_batch_id' => $scanBatch->id,
                ]);

            // Update akumulasi receive & balance per jancode
            $receiveTotal = 0;
            foreach ($jancodes as $j) {
                $received = JancodeLogs::where('jancode_id', $j->id)->where('is_submitted', 'true')->inStock()->count();
                $packing  = (int)$j->packing_list_qty_carton;
                $j->update([
                    'receive_qty_carton' => $received,
                    'balance'            => max(0, $packing - $received),
                ]);
                $receiveTotal += $received;
            }

            $scanBatch->update([
                'receive_qty' => $receiveTotal,
                'balance'     => max(0, $totalCarton - $receiveTotal),
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem saat submit batch scan: ' . $e->getMessage(),
                'error'   => $e->getMessage(),
            ], 500);
        }

        $state = $this->batchState($batch);

        return response()->json($state + [
            'success'           => true,
            'message'           => "Batch scan {$batchNo} berhasil disubmit ({$unsubmittedCount} karton).",
            'batch_no'          => $batchNo,
            'batch_id'          => $scanBatch->id,
            'batch_scanned_qty' => $unsubmittedCount,
            'surat_jalan'       => $suratJalan !== '' ? $suratJalan : null,
            'notes'             => $userNotes !== '' ? $userNotes : null,
        ]);
    }

    /**
     * Riwayat log scan tersimpan (jancode_logs) untuk packing list yang sedang
     * dibuka. Dipakai oleh card "Log Scanning Realtime" agar riwayat scan dari
     * sesi/perangkat sebelumnya tetap tampil saat halaman dibuka lagi.
     */
    public function scanLogHistory(Request $request)
    {
        $sessionId = ImportSession::currentId();
        $batch = ImportBatch::find((int) $request->input('import_batch_id'));

        if (!$batch || !$sessionId || (int) $batch->session_id !== (int) $sessionId) {
            return response()->json([
                'success' => false,
                'message' => 'Packing list tidak ditemukan pada session aktif.',
            ], 404);
        }

        $request->validate([
            'date' => 'nullable|date',
        ]);

        $query = JancodeLogs::with(['jancodeMaster', 'scanBatch', 'user'])
            ->where('session_id', $sessionId)
            ->where('packing_list_id', $batch->packing_list_id)
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->input('date'));
        }

        $limit = 200;
        $logs = $query->limit($limit)->get()->map(function ($log) {
            $j = $log->jancodeMaster;

            return [
                'id'           => $log->id,
                'barcode'      => $log->jancode ?: '-',
                'status'       => $log->status ?: JancodeLogs::STATUS_IN,
                'is_submitted' => filter_var($log->is_submitted, FILTER_VALIDATE_BOOLEAN),
                'time'         => $log->created_at ? $log->created_at->format('H:i:s') : '-',
                'date'         => $log->created_at ? $log->created_at->format('d/m/Y') : '-',
                'created_at'   => $log->created_at ? $log->created_at->format('Y-m-d H:i:s') : null,
                'batch_no'     => $log->scanBatch?->batch_no,
                'color'        => $j?->color,
                'size'         => $j?->size,
                'user'         => $log->user?->name,
            ];
        });

        return response()->json([
            'success'  => true,
            'data'     => $logs,
            'count'    => $logs->count(),
            'has_more' => $logs->count() >= $limit,
        ]);
    }

    /**
     * Riwayat Batch Scan.
     */
    public function batchHistory(Request $request)
    {
        if ($request->ajax()) {
            $request->validate([
                'session_id' => 'nullable|integer',
                'date_from'  => 'nullable|date',
                'date_to'    => 'nullable|date',
            ]);

            $importBatchId = $request->input('import_batch_id');

            $query = ScanBatch::with(['jancode', 'user', 'importBatch', 'session'])->latest();

            // Filter session bersifat opsional. Bila dikosongkan ("Semua Session"),
            // seluruh riwayat termasuk session sebelumnya tetap tampil.
            if ($request->filled('session_id')) {
                $query->where('session_id', $request->input('session_id'));
            }

            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->input('date_from'));
            }

            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->input('date_to'));
            }

            if ($importBatchId) {
                $query->where('import_batch_id', $importBatchId);
            }

            $batches = $query->get()->map(function ($b) {
                $j  = $b->jancode;
                $ib = $b->importBatch;
                return [
                    'id'          => $b->id,
                    'batch_no'    => $b->batch_no,
                    'created_at'  => $b->created_at ? $b->created_at->format('d/m/Y H:i:s') : '-',
                    'session_code'=> $b->session?->code ?: '-',
                    'packing_list'=> $ib ? ($ib->group_label . ' (' . strtoupper($ib->doc_type) . ' ' . $ib->revision_label . ')') : '-',
                    'jancode'     => $j ? $j->jancode : '-',
                    'color'       => $j ? $j->color : '-',
                    'size'        => $j ? $j->size : '-',
                    'style'       => $j ? $j->style : ($ib->style_number ?? '-'),
                    'country'     => $j ? ($j->country ?: $j->destination) : ($ib->destination ?? '-'),
                    'scanned_qty' => $b->scanned_qty,
                    'receive_qty' => $b->receive_qty,
                    'balance'     => $b->balance,
                    'total_qty'   => $b->total_qty,
                    'user_name'   => $b->user ? $b->user->name : 'Operator',
                    'surat_jalan' => $b->surat_jalan ?: '-',
                    'notes'       => $b->notes ?: '-',
                ];
            });

            return response()->json(['data' => $batches]);
        }

        return view('jancode.scan-batches', [
            'sessions'         => ImportSession::orderByDesc('id')->get(['id', 'code']),
            'currentSessionId' => ImportSession::currentId(),
        ]);
    }

    // DELETE — void scan terakhir
    public function voidLast(Request $request)
    {
        $batch = $this->resolveSessionBatch();
        if (!$batch) {
            return response()->json(['success' => false, 'error' => 'Pilih packing list terlebih dahulu'], 400);
        }

        if ($guard = $this->finishedGuard($batch)) {
            return response()->json($guard, 422);
        }

        try {
            DB::beginTransaction();

            $jancodeIds = Jancode::where('import_batch_id', $batch->id)->pluck('id')->all();

            $last = JancodeLogs::whereIn('jancode_id', $jancodeIds)
                ->where('is_submitted', 'false')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->first();

            if (!$last) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'error' => 'Tidak ada log scan terakhir yang bisa dibatalkan',
                ], 404);
            }

            $last->delete();
            DB::commit();

            return response()->json($this->batchState($batch) + ['success' => true]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function rollback(Request $request)
    {
        $batch = $this->resolveSessionBatch();
        if (!$batch) {
            return response()->json(['success' => false, 'message' => 'Pilih packing list terlebih dahulu'], 400);
        }

        if ($guard = $this->finishedGuard($batch)) {
            return response()->json($guard, 422);
        }

        try {
            DB::beginTransaction();

            $jancodeIds = Jancode::where('import_batch_id', $batch->id)->pluck('id')->all();

            $deletedRows = JancodeLogs::whereIn('jancode_id', $jancodeIds)
                ->where('is_submitted', 'false')
                ->delete();

            if ($deletedRows <= 0) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'error' => 'Tidak ada scan sementara yang bisa di-rollback',
                ], 404);
            }

            DB::commit();

            return response()->json($this->batchState($batch) + [
                'success' => true,
                'message' => "{$deletedRows} scan sementara berhasil dihapus.",
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem saat rollback scan',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function resetLock(Request $request)
    {
        session()->forget(self::SESSION_KEY);
        return response()->json(['success' => true]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function resolveSessionBatch(): ?ImportBatch
    {
        $batchId = session(self::SESSION_KEY);
        if (!$batchId) {
            return null;
        }

        $batch = ImportBatch::find($batchId);
        if (!$batch) {
            return null;
        }

        // Batch hanya boleh dipakai jika masih milik session aktif.
        $sessionId = ImportSession::currentId();
        if (!$sessionId || (int) $batch->session_id !== (int) $sessionId) {
            return null;
        }

        return $batch;
    }

    /**
     * Cari master jancode untuk barcode hasil scan.
     *
     * Mendukung:
     *  - jancode tunggal
     *  - case mix jancode: scan salah satu bagian dari "jan1-jan2"
     *  - karton broken: kode berakhiran "B"
     *
     * Exact match diprioritaskan supaya kode normal tidak tertukar dengan
     * varian broken.
     */
    private function findMasterForBarcode(int $batchId, string $barcode): ?Jancode
    {
        $master = $this->queryMasterForBarcode($batchId, $barcode);

        if ($master) {
            return $master;
        }

        // Fallback kompatibilitas: sebagian scanner mengirim prefix/suffix
        // non-jancode (mis. "]C1"). Coba lagi dengan digit saja, kecuali bila
        // kode memang varian broken (akhiran B).
        $digits = preg_replace('/[^0-9]/', '', $barcode);

        if (
            $digits !== ''
            && $digits !== $barcode
            && strlen($digits) === JancodeCode::LENGTH
            && ! JancodeCode::isBroken($barcode)
        ) {
            return $this->queryMasterForBarcode($batchId, $digits);
        }

        return null;
    }

    /**
     * Query kandidat master berdasarkan barcode (exact / bagian kode gabungan).
     */
    private function queryMasterForBarcode(int $batchId, string $barcode): ?Jancode
    {
        $parts = JancodeCode::parts($barcode);

        $candidates = Jancode::query()
            ->where('import_batch_id', $batchId)
            ->where('void', 'false')
            ->where(function ($query) use ($barcode, $parts) {
                $query->where('jancode', $barcode);

                foreach ($parts as $part) {
                    $digits = preg_replace('/[^0-9]/', '', $part);

                    if ($digits !== '') {
                        $query->orWhere('jancode', 'like', '%' . $digits . '%');
                    }
                }
            })
            ->lockForUpdate()
            ->get();

        return $candidates->first(
            fn (Jancode $row): bool => JancodeCode::normalize($row->jancode) === $barcode
        ) ?? $candidates->first(
            fn (Jancode $row): bool => JancodeCode::matches($row->jancode, $barcode)
        );
    }

    private function unsubmittedCount(int $batchId): int
    {
        $jancodeIds = Jancode::where('import_batch_id', $batchId)->pluck('id')->all();
        if (empty($jancodeIds)) {
            return 0;
        }

        return JancodeLogs::whereIn('jancode_id', $jancodeIds)->where('is_submitted', 'false')->count();
    }

    /**
     * Packing list yang sudah Finished tidak boleh menambah scan baru.
     * (Karton keluar tetap boleh — itu tahap berikutnya.)
     */
    private function finishedGuard(ImportBatch $batch): ?array
    {
        $packingList = $batch->packingList;

        if ($packingList && $packingList->isFinishedForBatch($batch->id)) {
            return [
                'success' => false,
                'error'   => 'finished',
                'message' => 'Packing list ini sudah Finished, tidak bisa scan lagi. '
                    . 'Report PDF/Excel tersedia di halaman Manage Packing List.',
            ];
        }

        return null;
    }

    /**
     * State lengkap packing list: info header, counter, dan baris jancode.
     *
     * @param bool $includeRows  false = hanya header + counter (payload ringan,
     *                           dipakai saat scan supaya respons jauh lebih cepat).
     */
    private function batchState(ImportBatch $batch, bool $includeRows = true): array
    {
        $jancodes = Jancode::where('import_batch_id', $batch->id)
            ->when($includeRows, fn($q) => $q
                ->orderByRaw('CASE WHEN carton_no_from IS NULL THEN 1 ELSE 0 END, carton_no_from ASC')
                ->orderBy('id'))
            ->get();

        $jancodeIds = $jancodes->pluck('id')->all();

        $unsubmittedMap = empty($jancodeIds)
            ? collect()
            : JancodeLogs::whereIn('jancode_id', $jancodeIds)
                ->where('is_submitted', 'false')
                ->selectRaw('jancode_id, COUNT(*) as total')
                ->groupBy('jancode_id')
                ->pluck('total', 'jancode_id');

        $rows = [];
        $totalCarton = 0;
        $scannedTotal = 0;
        $unsubmittedTotal = 0;
        $completedRows = 0;

        foreach ($jancodes as $j) {
            $unsubmitted = (int)($unsubmittedMap[$j->id] ?? 0);
            $row = $this->buildRowArray($j, $unsubmitted);

            $totalCarton      += $row['packing'];
            $scannedTotal     += $row['receive'];
            $unsubmittedTotal += $unsubmitted;

            if ($row['is_complete']) {
                $completedRows++;
            }

            if ($includeRows) {
                $rows[] = $row;
            }
        }

        return [
            'packing_list' => [
                'id'          => $batch->id,
                'packing_list_id' => $batch->packing_list_id,
                'batch_number'=> $batch->batch_number,
                'doc_type'    => $batch->doc_type ?: 'proforma',
                'group_label' => $batch->group_label,
                'style'       => $batch->style_number,
                'ocf'         => $batch->ocf_number,
                'destination' => $batch->destination,
                'warehouse'   => $batch->warehouse,
                'buyer'       => $batch->supplier_name,
                'revision_no' => $batch->revision_no ?: 1,
                'revision_label' => $batch->revision_label,
                'is_active'   => (bool)$batch->is_active,
                'import_date' => $batch->import_date ? $batch->import_date->format('d/m/Y') : '-',
            ],
            'counters' => [
                'scanned'     => $scannedTotal,
                'unsubmitted' => $unsubmittedTotal,
                'total'       => $totalCarton,
                'balance'     => max(0, $totalCarton - $scannedTotal - $unsubmittedTotal),
                'rows_total'  => $jancodes->count(),
                'rows_done'   => $completedRows,
                'is_complete' => ($totalCarton > 0 && ($scannedTotal + $unsubmittedTotal) >= $totalCarton),
            ],
            'rows' => $rows,
        ];
    }

    /**
     * Bentuk array satu baris jancode (dipakai batchState & respons scan).
     */
    private function buildRowArray(Jancode $j, int $unsubmitted): array
    {
        $packing  = (int)$j->packing_list_qty_carton;
        $received = (int)$j->receive_qty_carton;
        $balance  = max(0, $packing - $received - $unsubmitted);

        return [
            'id'             => $j->id,
            'jancode'        => $j->jancode,
            'color'          => $j->color,
            'size'           => $j->size,
            'carton_no'      => $j->carton_no_from
                ? ($j->carton_no_to && $j->carton_no_to !== $j->carton_no_from ? "{$j->carton_no_from}-{$j->carton_no_to}" : (string)$j->carton_no_from)
                : '-',
            'qty_per_carton' => (int)$j->qty_per_carton,
            'packing'        => $packing,
            'receive'        => $received,
            'unsubmitted'    => $unsubmitted,
            'balance'        => $balance,
            'is_complete'    => $balance === 0,
            'void'           => $j->void,
        ];
    }

    /**
     * Pencarian jancode (dipertahankan untuk kompatibilitas).
     */
    public function search(Request $request)
    {
        $q = $request->input('q');
        $query = Jancode::where('void', 'false');

        if ($q) {
            $query->where(function ($w) use ($q) {
                $w->where('jancode', 'LIKE', "%$q%")
                  ->orWhere('size', 'LIKE', "%$q%")
                  ->orWhere('buyer', 'LIKE', "%$q%")
                  ->orWhere('destination', 'LIKE', "%$q%")
                  ->orWhere('style', 'LIKE', "%$q%");
            });
        }

        $targets = $query->limit(20)->get();
        $results = [];

        foreach ($targets as $target) {
            $totalAll    = JancodeLogs::where('jancode_id', $target->id)->inStock()->count();
            $scanned     = JancodeLogs::where('jancode_id', $target->id)->where('is_submitted', 'true')->inStock()->count();
            $unsubmitted = JancodeLogs::where('jancode_id', $target->id)->where('is_submitted', 'false')->count();
            $balance     = (int)($target->packing_list_qty_carton ?: $target->qty) - $totalAll;

            $results[] = [
                'id'          => $target->id,
                'text'        => "{$target->jancode} | Size: {$target->size} | Qty: " . ($target->packing_list_qty_carton ?: $target->qty) . " (Sisa: {$balance})",
                'jancode'     => $target->jancode,
                'size'        => $target->size,
                'qty'         => $target->packing_list_qty_carton ?: $target->qty,
                'scanned'     => $scanned,
                'unsubmitted' => $unsubmitted,
                'total_all'   => $totalAll,
                'balance'     => $balance,
            ];
        }

        return response()->json($results);
    }
}
