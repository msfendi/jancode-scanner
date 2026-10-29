<?php

namespace App\Http\Controllers;

use App\Models\ImportBatch;
use App\Models\ImportSession;
use App\Models\Jancode;
use App\Models\PackingList;
use App\Models\PalletAddress;
use App\Models\PalletAssignment;
use App\Models\PalletAssignmentDetail;
use App\Models\PalletColorRule;
use App\Services\PalletColorService;
use App\Support\JancodeCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PalletController extends Controller
{
    /**
     * Halaman Utama Layout & Manage Pallet Location
     */
    public function index(Request $request)
    {
        // Pastikan 528 alamat pallet sudah di-seed
        $this->ensureAddressesSeeded();

        $selectedRow = $request->input('row', 'all'); // 'all' atau 'B', 'C', dll

        $currentSession = ImportSession::current();

        $query = PalletAddress::with(['assignment.details.jancode'])
            ->orderBy('row_label')
            ->orderBy('col_label')
            ->orderBy('position_no');

        if ($selectedRow !== 'all' && in_array(strtoupper($selectedRow), ['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'])) {
            $query->where('row_label', strtoupper($selectedRow));
        }

        $addresses = $query->get();

        // Warna pallet mengikuti negara + status isi (aturan dari DB).
        $colorService = app(PalletColorService::class);
        foreach ($addresses as $address) {
            $assignment = $address->assignment;
            $cartons = $assignment ? (int) $assignment->total_carton : 0;
            $buyer = $assignment?->details->first()?->jancode?->buyer;
            $visual = $colorService->resolve(
                $assignment?->country,
                $buyer,
                $cartons,
                (int) ($address->max_carton ?: 42)
            );

            $address->setAttribute('visual_color', $visual['color']);
            $address->setAttribute('visual_text_color', $visual['text_color']);
            $address->setAttribute('visual_text_shadow', $visual['text_shadow']);
            $address->setAttribute('visual_status', $visual['status']);
        }

        // Hitung statistik
        $allAddresses = PalletAddress::with('assignment')->get();
        $totalPositions = $allAddresses->count();
        $occupiedCount = $allAddresses->filter(fn($a) => $a->assignment && $a->assignment->total_carton > 0)->count();
        $vacantCount = $totalPositions - $occupiedCount;
        $totalCartons = $allAddresses->sum(fn($a) => $a->assignment ? $a->assignment->total_carton : 0);
        $totalCapacity = $totalPositions * 42;

        // Daftar Master Jancode yang memiliki sisa karton hasil scan yang siap ditempatkan
        $placedCartonsPerJancode = PalletAssignmentDetail::selectRaw('jancode_id, SUM(qty_carton) as placed')
            ->groupBy('jancode_id')
            ->pluck('placed', 'jancode_id');

        $availableJancodes = Jancode::where('void', 'false')
            ->whereIn('import_batch_id', ImportBatch::active()
                ->where('session_id', ImportSession::currentId() ?? 0)
                ->pluck('id'))
            // Packing list yang sudah Finish tidak boleh ditempatkan lagi.
            ->whereDoesntHave('packingList', fn ($q) => $q->whereNotNull('finished_at'))
            ->get()
            ->map(function ($item) use ($placedCartonsPerJancode) {
                $placed = (int)($placedCartonsPerJancode[$item->id] ?? 0);
                $totalPl = (int)($item->packing_list_qty_carton ?: $item->qty);
                $receiveQty = (int)($item->receive_qty_carton ?: 0);
                $balance = max(0, $totalPl - $receiveQty);
                // Akumulasi scan yang siap ditempatkan ke pallet:
                $unplacedScanned = max(0, $receiveQty - $placed);

                $item->placed_carton = $placed;
                $item->unplaced_carton = $unplacedScanned; // Hasil scan yang belum di pallet
                $item->total_pl_carton = $totalPl;
                $item->receive_qty = $receiveQty;
                $item->balance = $balance;
                return $item;
            })
            ->filter(fn($item) => $item->unplaced_carton > 0)
            ->values();

        // Kelompokkan alamat per Row untuk visualisasi (a & b horizontal depan, c & d belakang)
        $groupedRows = [];
        foreach ($addresses as $addr) {
            $r = $addr->row_label;
            $c = $addr->col_label;
            $p = $addr->position_no;
            $groupedRows[$r][$c][$p] = $addr;
        }

        // Ringkasan & Breakdown per Size per Color pada tiap Row (B-I)
        $rowSummaries = [];
        $allRows = ['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'];
        foreach ($allRows as $r) {
            $assignmentsInRow = PalletAssignment::with('details.jancode')
                ->whereHas('address', fn($q) => $q->where('row_label', $r))
                ->where('total_carton', '>', 0)
                ->get();

            $isLocked = $assignmentsInRow->isNotEmpty();
            $firstAssign = $assignmentsInRow->first();

            $colorBreakdown = [];
            $rowTotalCartons = 0;
            $rowTotalPcs = 0;
            $countriesSeen = [];
            $stylesSeen = [];

            foreach ($assignmentsInRow as $asg) {
                $rowTotalCartons += $asg->total_carton;
                if ($asg->country) { $countriesSeen[strtoupper(trim($asg->country))] = true; }
                if ($asg->style) { $stylesSeen[strtoupper(trim($asg->style))] = true; }
                foreach ($asg->details as $det) {
                    $jc = $det->jancode;
                    if ($jc) {
                        if ($jc->country) { $countriesSeen[strtoupper(trim($jc->country))] = true; }
                        if ($jc->style) { $stylesSeen[strtoupper(trim($jc->style))] = true; }
                        $cName = $jc->color;
                        $sName = $jc->size;
                        $ctn = (int)$det->qty_carton;
                        $pcs = $ctn * ($jc->qty_per_carton ?: 0);
                        $rowTotalPcs += $pcs;

                        if (!isset($colorBreakdown[$cName])) {
                            $colorBreakdown[$cName] = [
                                'sizes'         => [],
                                'total_cartons' => 0,
                                'total_pcs'     => 0,
                            ];
                        }
                        if (!isset($colorBreakdown[$cName]['sizes'][$sName])) {
                            $colorBreakdown[$cName]['sizes'][$sName] = 0;
                        }
                        $colorBreakdown[$cName]['sizes'][$sName] += $ctn;
                        $colorBreakdown[$cName]['total_cartons'] += $ctn;
                        $colorBreakdown[$cName]['total_pcs'] += $pcs;
                    }
                }
            }

            $firstJc = $firstAssign ? $firstAssign->details->first()?->jancode : null;
            $rowOcf = $firstAssign ? ($firstAssign->ocf ?: ($firstJc ? $firstJc->ocf : '-')) : null;

            $distinctCountries = collect(array_keys($countriesSeen));
            $distinctStyles = collect(array_keys($stylesSeen));

            $rowSummaries[$r] = [
                'is_locked'       => $isLocked,
                'country'         => $isLocked
                    ? ($distinctCountries->count() > 1 ? 'CAMPURAN (' . $distinctCountries->count() . ' negara)' : $distinctCountries->first())
                    : null,
                'country_list'    => $distinctCountries->values(),
                'style'           => $isLocked
                    ? ($distinctStyles->count() > 1 ? 'CAMPURAN (' . $distinctStyles->count() . ' style)' : $distinctStyles->first())
                    : null,
                'ocf'             => $rowOcf,
                'total_cartons'   => $rowTotalCartons,
                'total_pcs'       => $rowTotalPcs,
                'color_breakdown' => $colorBreakdown,
            ];
        }

        $palletColorRules = $colorService->rules();
        $palletLegend = $colorService->legend();

        return view('pallet.index', compact(
            'groupedRows',
            'addresses',
            'selectedRow',
            'totalPositions',
            'occupiedCount',
            'vacantCount',
            'totalCartons',
            'totalCapacity',
            'availableJancodes',
            'rowSummaries',
            'currentSession',
            'palletColorRules',
            'palletLegend'
        ));
    }

    /**
     * Visualisasi 3D layout pallet (Three.js).
     * Menampilkan gudang 528 posisi dalam bentuk 3D + animasi handlift in/out karton.
     */
    public function threeD()
    {
        $this->ensureAddressesSeeded();

        $currentSession = ImportSession::current();

        return view('pallet.three-d', compact('currentSession'));
    }

    /**
     * Data layout pallet untuk visualisasi 3D (dipanggil berkala untuk sinkronisasi).
     */
    public function threeDData()
    {
        $this->ensureAddressesSeeded();

        $addresses = PalletAddress::with(['assignment.details.jancode'])
            ->orderBy('row_label')
            ->orderBy('col_label')
            ->orderBy('position_no')
            ->get();

        $pallets = [];
        $occupied = 0;
        $cartons = 0;

        foreach ($addresses as $addr) {
            $asg = $addr->assignment;
            $qty = $asg ? (int) $asg->total_carton : 0;

            $items = [];
            if ($asg) {
                foreach ($asg->details as $det) {
                    $jc = $det->jancode;
                    $items[] = [
                        'jancode' => $jc ? $jc->jancode : null,
                        'color'   => $jc ? $jc->color : null,
                        'size'    => $jc ? $jc->size : null,
                        'qty'     => (int) $det->qty_carton,
                    ];
                }
            }

            if ($qty > 0) {
                $occupied++;
                $cartons += $qty;
            }

            $pallets[] = [
                'code'     => $addr->code,
                'row'      => $addr->row_label,
                'col'      => $addr->col_label,
                'pos'      => (int) $addr->position_no,
                'capacity' => (int) ($addr->max_carton ?: 42),
                'qty'      => $qty,
                'style'    => $asg ? $asg->style : null,
                'country'  => $asg ? $asg->country : null,
                'ocf'      => $asg ? $asg->ocf : null,
                'items'    => $items,
            ];
        }

        $total = count($pallets);

        return response()->json([
            'success'    => true,
            'stats'      => [
                'positions' => $total,
                'occupied'  => $occupied,
                'vacant'    => $total - $occupied,
                'cartons'   => $cartons,
                'capacity'  => $total * 42,
            ],
            'pallets'    => $pallets,
            'updated_at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Halaman display publik (tanpa auth) — board ringkas 1 layar + live SSE.
     */
    public function publicDisplay()
    {
        $this->ensureAddressesSeeded();

        $colorService = app(PalletColorService::class);

        return view('pallet.display', [
            'palletLegend' => $colorService->legend(),
        ]);
    }

    /**
     * Ubah mode negara per pallet: single | multi.
     */
    public function updateCountryMode(Request $request, $id)
    {
        $data = $request->validate([
            'country_mode' => 'required|in:single,multi',
        ]);

        $address = PalletAddress::with('assignment.details.jancode')->findOrFail($id);

        if ($data['country_mode'] === 'single' && $address->assignment) {
            $countries = collect($address->assignment->details)
                ->map(fn($d) => strtoupper(trim((string) ($d->jancode->country ?? ''))))
                ->filter()
                ->unique();

            if ($countries->count() > 1) {
                return response()->json([
                    'success' => false,
                    'message' => "Pallet {$address->code} sudah berisi " . $countries->count() . " negara (" . $countries->implode(', ') . "). Kosongkan/pindahkan item terlebih dahulu sebelum mengubah ke mode 1 negara.",
                ], 422);
            }
        }

        $address->country_mode = $data['country_mode'];
        $address->save();

        return response()->json([
            'success' => true,
            'country_mode' => $address->country_mode,
            'message' => $data['country_mode'] === 'multi'
                ? "Pallet {$address->code} sekarang boleh diisi beberapa negara."
                : "Pallet {$address->code} sekarang HANYA boleh 1 negara.",
        ]);
    }

    /**
     * Simpan perubahan warna pallet (hex) dari modal "Atur Warna Pallet".
     */
    public function updateColorRules(Request $request)
    {
        $data = $request->validate([
            'rules' => 'required|array|min:1',
            'rules.*.id' => 'required|integer|exists:pallet_color_rules,id',
            'rules.*.color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'rules.*.text_color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['rules'] as $row) {
                PalletColorRule::whereKey($row['id'])->update([
                    'color' => strtolower($row['color']),
                    'text_color' => strtolower($row['text_color']),
                ]);
            }
        });

        PalletColorService::flush();

        return response()->json([
            'success' => true,
            'message' => 'Warna pallet berhasil disimpan.',
        ]);
    }

    /**
     * Cari pallet yang berisi JAN code tertentu (untuk highlight di board).
     */
    public function findJancode(Request $request)
    {
        $raw = trim((string) $request->input('code'));
        if ($raw === '') {
            return response()->json(['success' => false, 'message' => 'JAN code tidak boleh kosong.'], 422);
        }

        $digits = preg_replace('/[^0-9]/', '', $raw);
        $term = $digits !== '' ? $digits : $raw;

        // Input kode gabungan "jan1-jan2" → pakai bagian pertama untuk LIKE
        // supaya karton mix jancode tetap ketemu.
        $parts = JancodeCode::parts($raw);
        if (count($parts) > 1) {
            $first = preg_replace('/[^0-9]/', '', $parts[0]);
            if ($first !== '') {
                $term = $first;
            }
        }

        $rows = PalletAssignmentDetail::query()
            ->join('pallet_assignment', 'pallet_assignment.id', '=', 'pallet_assignment_detail.assignment_id')
            ->join('pallet_address', 'pallet_address.id', '=', 'pallet_assignment.pallet_address_id')
            ->join('jancode', 'jancode.id', '=', 'pallet_assignment_detail.jancode_id')
            ->where('jancode.jancode', 'like', '%' . $term . '%')
            ->groupBy('pallet_address.id', 'pallet_address.code', 'pallet_address.row_label', 'pallet_address.col_label', 'pallet_address.position_no')
            ->selectRaw('pallet_address.id, pallet_address.code, pallet_address.row_label, pallet_address.col_label, pallet_address.position_no, SUM(pallet_assignment_detail.qty_carton) as qty')
            ->orderBy('pallet_address.code')
            ->get();

        return response()->json([
            'success' => true,
            'query'   => $term,
            'count'   => $rows->count(),
            'pallets' => $rows,
        ]);
    }

    /**
     * Detail pallet untuk halaman public (tanpa auth).
     */
    public function publicDetail($id)
    {
        $address = PalletAddress::with(['assignment.details.jancode'])->findOrFail($id);
        $assignment = $address->assignment;

        $items = [];
        $countries = [];
        $totalPcs = 0;

        if ($assignment) {
            foreach ($assignment->details as $detail) {
                $jancode = $detail->jancode;
                $qtyCarton = (int) $detail->qty_carton;
                $pcsPerCarton = (int) ($jancode?->qty_per_carton ?? 0);
                $pcs = $qtyCarton * $pcsPerCarton;
                $totalPcs += $pcs;

                $country = strtoupper(trim((string) ($jancode?->country ?? '')));
                if ($country !== '') {
                    $countries[$country] = true;
                }

                $items[] = [
                    'jancode'        => $jancode?->jancode ?? '-',
                    'color'          => $jancode?->color ?? '-',
                    'size'           => $jancode?->size ?? '-',
                    'qty_carton'     => $qtyCarton,
                    'pcs_per_carton' => $pcsPerCarton,
                    'total_pcs'      => $pcs,
                ];
            }
        }

        $cartons = $assignment ? (int) $assignment->total_carton : 0;
        $maxCarton = (int) ($address->max_carton ?: 42);
        $buyer = $assignment?->details->first()?->jancode?->buyer;
        $visual = app(PalletColorService::class)->resolve($assignment?->country, $buyer, $cartons, $maxCarton);

        return response()->json([
            'success' => true,
            'pallet'  => [
                'id'           => $address->id,
                'code'         => $address->code,
                'row'          => $address->row_label,
                'col'          => $address->col_label,
                'pos'          => $address->position_no,
                'max_carton'   => $maxCarton,
                'country_mode' => $address->country_mode ?: 'single',
                'cartons'      => $cartons,
                'style'        => $assignment->style ?? null,
                'ocf'          => $assignment->ocf ?? null,
                'countries'    => array_keys($countries),
                'total_pcs'    => $totalPcs,
                'status'       => $visual['status'],
                'color'        => $visual['color'],
                'text_color'   => $visual['text_color'],
                'text_shadow'  => $visual['text_shadow'],
            ],
            'items' => $items,
        ]);
    }

    /**
     * SSE stream untuk live update pallet (tanpa Vite / WebSocket).
     * Emit event 'pallet' tiap ada perubahan assignment/detail.
     */
    public function stream(Request $request): StreamedResponse
    {
        if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
        $lastHash = (string) $request->query('t', '');
        $maxSeconds = 55;
        $pollMs = 2000;
        return new StreamedResponse(function () use ($lastHash, $maxSeconds, $pollMs) {
            set_time_limit(0);
            ignore_user_abort(true);
            if (ob_get_level() > 0) { @ob_end_clean(); }
            $start = time();
            $prevHash = $lastHash;
            $initial = $this->buildLiveSnapshot();
            $initialHash = $initial['hash'];
            if ($prevHash === '' || $prevHash !== $initialHash) {
                echo 'event: pallet' . PHP_EOL;
                echo 'data: ' . json_encode($initial['payload'], JSON_UNESCAPED_UNICODE) . PHP_EOL . PHP_EOL;
                @ob_flush(); @flush();
                $prevHash = $initialHash;
            } else {
                echo ': connected' . PHP_EOL . PHP_EOL;
                @ob_flush(); @flush();
            }
            while ((time() - $start) < $maxSeconds) {
                if (connection_aborted()) break;
                usleep($pollMs * 1000);
                $snap = $this->buildLiveSnapshot();
                if ($snap['hash'] !== $prevHash) {
                    echo 'event: pallet' . PHP_EOL;
                    echo 'data: ' . json_encode($snap['payload'], JSON_UNESCAPED_UNICODE) . PHP_EOL . PHP_EOL;
                    @ob_flush(); @flush();
                    $prevHash = $snap['hash'];
                } else {
                    echo ': hb ' . time() . PHP_EOL . PHP_EOL;
                    @ob_flush(); @flush();
                }
            }
            echo 'event: done' . PHP_EOL;
            echo 'data: closing' . PHP_EOL . PHP_EOL;
            @ob_flush(); @flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
            'Pragma' => 'no-cache',
        ]);
    }

    private function buildLiveSnapshot(): array
    {
        $colorService = app(PalletColorService::class);

        $addresses = PalletAddress::with(['assignment.details.jancode'])
            ->orderBy('id')
            ->get(['id','code','row_label','col_label','position_no','max_carton','country_mode']);
        $assignments = PalletAssignment::with(['details.jancode','address'])->get();
        $maxA = PalletAssignment::max('updated_at') ?: '0';
        $maxD = PalletAssignmentDetail::max('updated_at') ?: '0';
        $maxAddr = PalletAddress::max('updated_at') ?: '0';
        $maxRule = PalletColorRule::max('updated_at') ?: '0';
        $sum = (int) $assignments->sum('total_carton');
        $cnt = $assignments->count();
        $hash = md5($maxA . '|' . $maxD . '|' . $maxAddr . '|' . $maxRule . '|' . $sum . '|' . $cnt);
        $pallets = [];
        foreach ($addresses as $a) {
            $asg = $a->assignment;
            $cartons = $asg ? (int) $asg->total_carton : 0;
            $maxCarton = (int) ($a->max_carton ?: 42);
            $buyer = $asg?->details->first()?->jancode?->buyer;
            $visual = $colorService->resolve($asg?->country, $buyer, $cartons, $maxCarton);

            $pallets[] = [
                'id' => $a->id,
                'code' => $a->code,
                'row' => $a->row_label,
                'col' => $a->col_label,
                'pos' => $a->position_no,
                'cartons' => $cartons,
                'style' => $asg ? $asg->style : null,
                'country' => $asg ? $asg->country : null,
                'ocf' => $asg ? ($asg->ocf ?? null) : null,
                'country_mode' => $a->country_mode ?: 'single',
                'max_carton' => $maxCarton,
                'status' => $visual['status'],
                'color' => $visual['color'],
                'text_color' => $visual['text_color'],
                'text_shadow' => $visual['text_shadow'],
            ];
        }
        $occupied = collect($pallets)->where('cartons','>',0)->count();
        $total = count($pallets);
        $vacant = $total - $occupied;
        $rowSummaries = [];
        foreach (['B','C','D','E','F','G','H','I'] as $r) {
            $inRow = $assignments->filter(fn($x) => $x->address && $x->address->row_label === $r && $x->total_carton > 0);
            $first = $inRow->first();
            $totalCartons = (int) $inRow->sum('total_carton');
            $totalPcs = 0;
            $colorBreakdown = [];
            $countriesSeen = [];
            $stylesSeen = [];
            foreach ($inRow as $asg) {
                if ($asg->country) { $countriesSeen[strtoupper(trim($asg->country))] = true; }
                if ($asg->style) { $stylesSeen[strtoupper(trim($asg->style))] = true; }
                foreach ($asg->details as $det) {
                    $jc = $det->jancode;
                    if (!$jc) continue;
                    if ($jc->country) { $countriesSeen[strtoupper(trim($jc->country))] = true; }
                    if ($jc->style) { $stylesSeen[strtoupper(trim($jc->style))] = true; }
                    $cName = $jc->color ?: '-';
                    $sName = $jc->size ?: '-';
                    $ctn = (int)$det->qty_carton;
                    $pcs = $ctn * (int)($jc->qty_per_carton ?: 0);
                    $totalPcs += $pcs;
                    if (!isset($colorBreakdown[$cName])) {
                        $colorBreakdown[$cName] = ['sizes'=>[],'total_cartons'=>0,'total_pcs'=>0];
                    }
                    if (!isset($colorBreakdown[$cName]['sizes'][$sName])) $colorBreakdown[$cName]['sizes'][$sName]=0;
                    $colorBreakdown[$cName]['sizes'][$sName]+= $ctn;
                    $colorBreakdown[$cName]['total_cartons']+= $ctn;
                    $colorBreakdown[$cName]['total_pcs']+= $pcs;
                }
            }
            if ($first) {
                $firstJc = $first->details->first()?->jancode;
                $rowCountries = array_keys($countriesSeen);
                $rowStyles = array_keys($stylesSeen);
                $rowSummaries[$r] = [
                    'is_locked' => true,
                    'country' => count($rowCountries) > 1 ? ('CAMPURAN (' . count($rowCountries) . ' negara)') : ($rowCountries[0] ?? $first->country),
                    'country_list' => $rowCountries,
                    'style' => count($rowStyles) > 1 ? ('CAMPURAN (' . count($rowStyles) . ' style)') : ($rowStyles[0] ?? $first->style),
                    'ocf' => $first->ocf ?: ($firstJc ? ($firstJc->ocf ?? null) : null),
                    'total_cartons' => $totalCartons,
                    'total_pcs' => $totalPcs,
                    'color_breakdown' => $colorBreakdown,
                ];
            } else {
                $rowSummaries[$r] = ['is_locked'=>false,'country'=>null,'country_list'=>[],'style'=>null,'ocf'=>null,'total_cartons'=>0,'total_pcs'=>0,'color_breakdown'=>[]];
            }
        }
        $payload = [
            'ts' => time(),
            'hash' => $hash,
            'stats' => [
                'total' => $total,
                'occupied' => $occupied,
                'vacant' => $vacant,
                'totalCartons' => $sum,
                'totalCapacity' => $total * 42,
                'pct' => $total ? round($occupied/$total*100,1) : 0,
            ],
            'pallets' => $pallets,
            'rows' => $rowSummaries,
        ];
        return ['hash'=>$hash, 'payload'=>$payload];
    }

    /**
     * Detail satu pallet (JSON)
     */
    public function getDetail($id)
    {
        $address = PalletAddress::with(['assignment.details.jancode'])->findOrFail($id);

        $assignment = $address->assignment;
        $currentCartons = $assignment ? (int)$assignment->total_carton : 0;
        $availableSpace = max(0, $address->max_carton - $currentCartons);

        $items = [];
        if ($assignment && $assignment->details) {
            foreach ($assignment->details as $d) {
                $j = $d->jancode;
                $totalPl = $j ? (int)($j->packing_list_qty_carton ?: $j->qty) : 0;
                $receiveQty = $j ? (int)($j->receive_qty_carton ?: 0) : 0;
                $balance = $j ? max(0, $totalPl - $receiveQty) : 0;
                $items[] = [
                    'detail_id'      => $d->id,
                    'jancode_id'     => $d->jancode_id,
                    'jan_code'       => $j ? $j->jancode : '-',
                    'color'          => $j ? $j->color : '-',
                    'size'           => $j ? $j->size : '-',
                    'qty_per_carton' => $j ? ($j->qty_per_carton ?: 0) : 0,
                    'qty_carton'     => (int)$d->qty_carton,
                    'total_pcs'      => $j ? ((int)$d->qty_carton * ($j->qty_per_carton ?: 0)) : 0,
                    'total_qty'      => $totalPl,
                    'receive_qty'    => $receiveQty,
                    'balance'        => $balance,
                ];
            }
        }

        return response()->json([
            'success'         => true,
            'address'         => [
                'id'          => $address->id,
                'code'        => $address->code,
                'row'         => $address->row_label,
                'col'         => $address->col_label,
                'pos'         => $address->position_no,
                'max_carton'  => $address->max_carton,
                'country_mode' => $address->country_mode ?: 'single',
                'is_occupied' => $currentCartons > 0,
            ],
            'assignment'      => $assignment ? [
                'id'           => $assignment->id,
                'style'        => $assignment->style,
                'country'      => $assignment->country,
                'ocf'          => $assignment->ocf,
                'total_carton' => $currentCartons,
            ] : null,
            'available_space' => $availableSpace,
            'items'           => $items,
        ]);
    }

    /**
     * Plot / Tambah Penempatan Manual Karton ke Pallet (BR-08 s/d BR-12 + Aturan Row Lock)
     */
    public function storeAssignment(Request $request)
    {
        $request->validate([
            'pallet_address_id' => 'required|exists:pallet_address,id',
            'jancode_id'        => 'required|exists:jancode,id',
            'qty_carton'        => 'nullable|integer|min:1',
        ]);

        $addressId = $request->input('pallet_address_id');
        $jancodeId = $request->input('jancode_id');
        $requestedQty = $request->input('qty_carton') ? (int)$request->input('qty_carton') : null;

        return DB::transaction(function () use ($addressId, $jancodeId, $requestedQty) {
            $address = PalletAddress::lockForUpdate()->findOrFail($addressId);
            $jancode = Jancode::lockForUpdate()->findOrFail($jancodeId);
            $jancode->loadMissing('packingList');

            if ($jancode->packingList?->isFinishedForBatch((int) $jancode->import_batch_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Packing list untuk jancode ini sudah Finished. Karton tidak bisa ditempatkan lagi ke pallet.',
                ], 422);
            }

            $activeBatch = ImportBatch::find($jancode->import_batch_id);
            if (!$activeBatch || !$activeBatch->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => "Jancode ini milik revisi packing list yang sudah tidak aktif. Silakan muat packing list terbaru.",
                ], 422);
            }

            $jancodeCountry = strtoupper(trim($jancode->country ?: ($jancode->destination ?: '')));
            $jancodeStyle = strtoupper(trim($jancode->style ?: ''));
            $jancodeOcf = strtoupper(trim($jancode->ocf ?: ($jancode->cpo ?: '')));

            $assignment = PalletAssignment::where('pallet_address_id', $addressId)->lockForUpdate()->first();
            $currentCartons = $assignment ? (int)$assignment->total_carton : 0;
            $palletSpace = max(0, $address->max_carton - $currentCartons);

            if ($palletSpace <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Pallet {$address->code} sudah penuh (kapasitas 42/42)."
                ], 422);
            }

            // Hitung akumulasi karton hasil scan yang siap ditempatkan
            $alreadyPlaced = (int)PalletAssignmentDetail::where('jancode_id', $jancodeId)->sum('qty_carton');
            $receiveQty = (int)($jancode->receive_qty_carton ?: 0);
            $totalPl = (int)($jancode->packing_list_qty_carton ?: $jancode->qty);
            $availableScannedToPlace = max(0, $receiveQty - $alreadyPlaced);

            if ($availableScannedToPlace <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Tidak ada karton hasil scan yang tersedia untuk ditempatkan. (Total PL: {$totalPl}, Di-scan: {$receiveQty}, Sudah di Pallet: {$alreadyPlaced}). Silakan lakukan scan terlebih dahulu di modul Scanner."
                ], 422);
            }

            // Jumlah karton yang ditempatkan boleh dikustom (tidak harus full).
            // Sisa karton hasil scan tetap bisa ditempatkan di pallet lain.
            if ($requestedQty !== null) {
                if ($requestedQty > $availableScannedToPlace) {
                    return response()->json([
                        'success' => false,
                        'error'   => 'exceed_scanned',
                        'message' => "Jumlah yang ditempatkan ({$requestedQty} karton) melebihi sisa hasil scan ({$availableScannedToPlace} karton). Maksimal {$availableScannedToPlace} karton untuk jancode ini.",
                    ], 422);
                }

                if ($requestedQty > $palletSpace) {
                    return response()->json([
                        'success' => false,
                        'error'   => 'exceed_capacity',
                        'message' => "Jumlah yang ditempatkan ({$requestedQty} karton) melebihi sisa kapasitas Pallet {$address->code} ({$palletSpace} karton).",
                    ], 422);
                }

                $addQty = $requestedQty;
            } else {
                // Tanpa input manual: isi otomatis sebatas scan & kapasitas pallet.
                $addQty = min($availableScannedToPlace, $palletSpace);
            }

            if ($addQty <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Tidak ada kapasitas pallet yang tersedia untuk penempatan ini."
                ], 422);
            }

            // 1. ATURAN ROW LOCKING: Seluruh Row B-I harus satu Style & OCF.
            //    Country TIDAK lagi dikunci per row supaya pallet mode "multi negara" bisa dipakai.
            $rowActiveAssignment = PalletAssignment::with('details.jancode')
                ->whereHas('address', fn($q) => $q->where('row_label', $address->row_label))
                ->where('total_carton', '>', 0)
                ->first();

            if ($rowActiveAssignment) {
                $rowStyle = strtoupper(trim($rowActiveAssignment->style));
                $firstJc = $rowActiveAssignment->details->first()?->jancode;
                $rowOcf = strtoupper(trim($rowActiveAssignment->ocf ?: ($firstJc ? $firstJc->ocf : '')));

                if ($rowStyle !== $jancodeStyle || ($rowOcf && $rowOcf !== $jancodeOcf)) {
                    return response()->json([
                        'success' => false,
                        'error'   => 'row_locked',
                        'message' => "Pelanggaran Aturan Row: Seluruh Row {$address->row_label} sudah dikunci untuk Style [{$rowStyle}], OCF [{$rowOcf}]. Pallet pada Row {$address->row_label} tidak dapat dicampur dengan Style/OCF lain."
                    ], 422);
                }
            }

            // 2. BR-12: Total karton yang ditempatkan tidak boleh melebihi sisa hasil scan
            if ($alreadyPlaced + $addQty > $receiveQty) {
                $sisaBisa = max(0, $receiveQty - $alreadyPlaced);
                return response()->json([
                    'success' => false,
                    'message' => "Total penempatan item ini ({$alreadyPlaced} + {$addQty}) melebihi hasil scan yang tersedia ({$receiveQty}). Maksimal sisa hasil scan: {$sisaBisa} karton."
                ], 422);
            }

            $assignment = PalletAssignment::where('pallet_address_id', $addressId)->lockForUpdate()->first();

            if ($assignment && $assignment->total_carton > 0) {
                // BR-09: Style selalu dikunci per pallet.
                $existingCountry = strtoupper(trim($assignment->country));
                $existingStyle = strtoupper(trim($assignment->style));

                if ($existingStyle !== $jancodeStyle) {
                    return response()->json([
                        'success' => false,
                        'error'   => 'style_locked',
                        'message' => "Pelanggaran BR-09: Pallet {$address->code} sudah terisi Style [{$existingStyle}]. Tidak bisa mencampur dengan Style [{$jancodeStyle}]."
                    ], 422);
                }

                // Mode negara per pallet:
                // - single (default): hanya boleh 1 negara
                // - multi: boleh beberapa negara
                if (!$address->isMultiCountry() && $existingCountry !== $jancodeCountry) {
                    return response()->json([
                        'success' => false,
                        'error'   => 'country_locked',
                        'message' => "Pallet {$address->code} diset HANYA 1 NEGARA (saat ini: {$existingCountry}). Negara [{$jancodeCountry}] tidak bisa ditempatkan di sini. Ubah toggle pallet ke \"Multi Negara\" bila ingin mencampur negara."
                    ], 422);
                }

                // BR-08 & BR-11: Maksimum isi satu alamat = 42 karton
                if ($assignment->total_carton + $addQty > $address->max_carton) {
                    $sisaSlot = max(0, $address->max_carton - $assignment->total_carton);
                    return response()->json([
                        'success' => false,
                        'message' => "Pelanggaran BR-11: Kapasitas Pallet {$address->code} terlampaui. Total saat ini: {$assignment->total_carton}, sisa slot: {$sisaSlot}. Menambahkan {$addQty} karton akan melebihi batas 42 karton."
                    ], 422);
                }

                // Cari apakah jancode yang sama sudah ada di pallet ini
                $detail = PalletAssignmentDetail::where('assignment_id', $assignment->id)
                    ->where('jancode_id', $jancodeId)
                    ->first();

                if ($detail) {
                    $detail->increment('qty_carton', $addQty);
                } else {
                    PalletAssignmentDetail::create([
                        'assignment_id'   => $assignment->id,
                        'jancode_id'      => $jancodeId,
                        'packing_list_id' => $jancode->packing_list_id,
                        'session_id'      => $activeBatch->session_id,
                        'qty_carton'      => $addQty,
                    ]);
                }

                $assignment->increment('total_carton', $addQty);

            } else {
                // Pallet masih kosong
                if ($addQty > $address->max_carton) {
                    return response()->json([
                        'success' => false,
                        'message' => "Pelanggaran BR-11: Jumlah karton ({$addQty}) melebihi batas maksimal pallet 42 karton."
                    ], 422);
                }

                $this->ensureOcfColumnExists();
                $hasOcfCol = \Illuminate\Support\Facades\Schema::hasColumn('pallet_assignment', 'ocf');

                $payload = [
                    'style'        => $jancodeStyle,
                    'country'      => $jancodeCountry,
                    'total_carton' => $addQty,
                ];
                if ($hasOcfCol) {
                    $payload['ocf'] = $jancodeOcf;
                }

                if (!$assignment) {
                    $payload['pallet_address_id'] = $addressId;
                    $assignment = PalletAssignment::create($payload);
                } else {
                    $assignment->update($payload);
                }

                PalletAssignmentDetail::create([
                    'assignment_id'   => $assignment->id,
                    'jancode_id'      => $jancodeId,
                    'packing_list_id' => $jancode->packing_list_id,
                    'session_id'      => $activeBatch->session_id,
                    'qty_carton'      => $addQty,
                ]);
            }

            $remaining = max(0, $availableScannedToPlace - $addQty);
            $message   = "Berhasil menempatkan {$addQty} karton ke Pallet {$address->code}.";

            if ($remaining > 0) {
                $message .= " Sisa {$remaining} karton masih bisa ditempatkan di pallet lain.";
            }

            return response()->json([
                'success'   => true,
                'message'   => $message,
                'placed'    => $addQty,
                'remaining' => $remaining,
            ]);
        });
    }

    /**
     * Pindah Karton Antar Pallet (FR-06 Manage Perpindahan Karton)
     */
    public function moveCarton(Request $request)
    {
        $request->validate([
            'source_detail_id'         => 'required|exists:pallet_assignment_detail,id',
            'target_pallet_address_id' => 'required|exists:pallet_address,id',
            'qty_carton'               => 'required|integer|min:1',
        ]);

        $detailId = $request->input('source_detail_id');
        $targetAddressId = $request->input('target_pallet_address_id');
        $moveQty = (int)$request->input('qty_carton');

        return DB::transaction(function () use ($detailId, $targetAddressId, $moveQty) {
            $sourceDetail = PalletAssignmentDetail::with(['assignment.address', 'jancode'])->lockForUpdate()->findOrFail($detailId);
            $sourceAssignment = $sourceDetail->assignment;
            $sourceAddress = $sourceAssignment->address;
            $jancode = $sourceDetail->jancode;

            if ($moveQty > $sourceDetail->qty_carton) {
                return response()->json([
                    'success' => false,
                    'message' => "Jumlah yang ingin dipindahkan ({$moveQty}) lebih besar dari karton yang ada di pallet sumber ({$sourceDetail->qty_carton})."
                ], 422);
            }

            if ($sourceAddress->id == $targetAddressId) {
                return response()->json([
                    'success' => false,
                    'message' => "Pallet tujuan tidak boleh sama dengan pallet asal."
                ], 422);
            }

            $targetAddress = PalletAddress::lockForUpdate()->findOrFail($targetAddressId);
            $targetAssignment = PalletAssignment::where('pallet_address_id', $targetAddressId)->lockForUpdate()->first();

            $jancodeCountry = strtoupper(trim($sourceAssignment->country));
            $jancodeStyle = strtoupper(trim($sourceAssignment->style));
            $sourceOcf = strtoupper(trim($sourceAssignment->ocf ?: ($jancode->ocf ?: '')));

            // 1. ATURAN ROW LOCKING: Target row tidak boleh punya country, style, atau ocf berbeda
            $targetRowActiveAssignment = PalletAssignment::with('details.jancode')
                ->whereHas('address', fn($q) => $q->where('row_label', $targetAddress->row_label))
                ->where('total_carton', '>', 0)
                ->where('pallet_address_id', '!=', $sourceAddress->id)
                ->first();

            if ($targetRowActiveAssignment) {
                $targetRowCountry = strtoupper(trim($targetRowActiveAssignment->country));
                $targetRowStyle = strtoupper(trim($targetRowActiveAssignment->style));
                $firstTargetJc = $targetRowActiveAssignment->details->first()?->jancode;
                $targetRowOcf = strtoupper(trim($targetRowActiveAssignment->ocf ?: ($firstTargetJc ? $firstTargetJc->ocf : '')));

                if ($targetRowCountry !== $jancodeCountry || $targetRowStyle !== $jancodeStyle || ($targetRowOcf && $sourceOcf && $targetRowOcf !== $sourceOcf)) {
                    return response()->json([
                        'success' => false,
                        'message' => "Pelanggaran Aturan Row: Row {$targetAddress->row_label} sudah dikunci untuk Country [{$targetRowCountry}], Style [{$targetRowStyle}], OCF [{$targetRowOcf}]. Tidak dapat memindahkan karton ke Row {$targetAddress->row_label}."
                    ], 422);
                }
            }

            // Validasi di target pallet
            if ($targetAssignment && $targetAssignment->total_carton > 0) {
                $targetCountry = strtoupper(trim($targetAssignment->country));
                $targetStyle = strtoupper(trim($targetAssignment->style));

                // BR-09: Style dan country harus cocok
                if ($targetCountry !== $jancodeCountry || $targetStyle !== $jancodeStyle) {
                    return response()->json([
                        'success' => false,
                        'message' => "Tidak bisa memindahkan ke Pallet {$targetAddress->code}: Pallet tujuan memiliki Style [{$targetStyle}] / Country [{$targetCountry}], sedangkan karton yang dipindah adalah Style [{$jancodeStyle}] / Country [{$jancodeCountry}]."
                    ], 422);
                }

                // BR-11: Cek batas 42 karton
                if ($targetAssignment->total_carton + $moveQty > $targetAddress->max_carton) {
                    $sisaSlot = max(0, $targetAddress->max_carton - $targetAssignment->total_carton);
                    return response()->json([
                        'success' => false,
                        'message' => "Pallet tujuan {$targetAddress->code} tidak memiliki cukup slot. Sisa kapasitas: {$sisaSlot} karton, karton yang akan dipindah: {$moveQty}."
                    ], 422);
                }

                // Tambahkan detail di target
                $targetDetail = PalletAssignmentDetail::where('assignment_id', $targetAssignment->id)
                    ->where('jancode_id', $jancode->id)
                    ->first();

                if ($targetDetail) {
                    $targetDetail->increment('qty_carton', $moveQty);
                } else {
                    PalletAssignmentDetail::create([
                        'assignment_id'   => $targetAssignment->id,
                        'jancode_id'      => $jancode->id,
                        'packing_list_id' => $jancode->packing_list_id,
                        'session_id'      => $sourceDetail->session_id,
                        'qty_carton'      => $moveQty,
                    ]);
                }

                $targetAssignment->increment('total_carton', $moveQty);

            } else {
                // Target kosong
                if ($moveQty > $targetAddress->max_carton) {
                    return response()->json([
                        'success' => false,
                        'message' => "Karton yang dipindah ({$moveQty}) melebihi kapasitas pallet tujuan (42 karton)."
                    ], 422);
                }

                $this->ensureOcfColumnExists();
                $hasOcfCol = \Illuminate\Support\Facades\Schema::hasColumn('pallet_assignment', 'ocf');

                $movePayload = [
                    'style'        => $jancodeStyle,
                    'country'      => $jancodeCountry,
                    'total_carton' => $moveQty,
                ];
                if ($hasOcfCol) {
                    $movePayload['ocf'] = $sourceOcf;
                }

                if (!$targetAssignment) {
                    $movePayload['pallet_address_id'] = $targetAddressId;
                    $targetAssignment = PalletAssignment::create($movePayload);
                } else {
                    $targetAssignment->update($movePayload);
                }

                PalletAssignmentDetail::create([
                    'assignment_id'   => $targetAssignment->id,
                    'jancode_id'      => $jancode->id,
                    'packing_list_id' => $jancode->packing_list_id,
                    'session_id'      => $sourceDetail->session_id,
                    'qty_carton'      => $moveQty,
                ]);
            }

            // Kurangi karton di source
            if ($sourceDetail->qty_carton == $moveQty) {
                $sourceDetail->delete();
            } else {
                $sourceDetail->decrement('qty_carton', $moveQty);
            }

            $sourceAssignment->decrement('total_carton', $moveQty);

            // Jika source pallet sekarang kosong, hapus assignment
            if ($sourceAssignment->total_carton <= 0) {
                $sourceAssignment->delete();
            }

            return response()->json([
                'success' => true,
                'message' => "Berhasil memindahkan {$moveQty} karton dari Pallet {$sourceAddress->code} ke Pallet {$targetAddress->code}."
            ]);
        });
    }

    /**
     * Hapus satu item penempatan dari pallet
     */
    public function removeDetail($detailId)
    {
        return DB::transaction(function () use ($detailId) {
            $detail = PalletAssignmentDetail::with('assignment')->lockForUpdate()->findOrFail($detailId);
            $assignment = $detail->assignment;
            $qty = $detail->qty_carton;

            $detail->delete();

            $assignment->decrement('total_carton', $qty);

            if ($assignment->total_carton <= 0) {
                $assignment->delete();
            }

            return response()->json([
                'success' => true,
                'message' => "Berhasil mengeluarkan {$qty} karton dari pallet."
            ]);
        });
    }

    /**
     * Kosongkan seluruh pallet
     */
    public function clearPallet($addressId)
    {
        return DB::transaction(function () use ($addressId) {
            $assignment = PalletAssignment::where('pallet_address_id', $addressId)->first();
            if ($assignment) {
                $assignment->details()->delete();
                $assignment->delete();
            }

            return response()->json([
                'success' => true,
                'message' => "Pallet berhasil dikosongkan."
            ]);
        });
    }

    /**
     * Tarik karton milik satu packing list dari semua pallet yang dituju.
     * Dipanggil setelah user mengonfirmasi "Kosongkan pallet?" saat Finish.
     * Hanya detail milik batch yang di-Finish yang dihapus; karton packing
     * list lain (jika ada di pallet yang sama) tidak ikut dikeluarkan.
     */
    public function clearForPackingList(Request $request, PackingList $packingList)
    {
        $sessionId = ImportSession::currentId();
        if (! $sessionId || (int) $packingList->session_id !== (int) $sessionId) {
            return response()->json([
                'success' => false,
                'message' => 'Packing list tidak ditemukan pada session aktif.',
            ], 404);
        }

        if (! $packingList->isFinished()) {
            return response()->json([
                'success' => false,
                'message' => 'Packing list belum berstatus Finished.',
            ], 422);
        }

        $batchId = (int) ($packingList->finished_import_batch_id ?: 0);

        $result = DB::transaction(function () use ($packingList, $batchId) {
            $query = PalletAssignmentDetail::with(['assignment.address'])
                ->where('packing_list_id', $packingList->id);

            if ($batchId > 0) {
                $query->whereHas('jancode', fn ($q) => $q->where('import_batch_id', $batchId));
            }

            $details = $query->lockForUpdate()->get();

            $clearedCartons = 0;
            $codes = [];

            foreach ($details->groupBy('assignment_id') as $assignmentId => $rows) {
                $assignment = PalletAssignment::with('address')->lockForUpdate()->find($assignmentId);

                if (! $assignment) {
                    continue;
                }

                $qty = (int) $rows->sum('qty_carton');
                $code = $assignment->address?->code;

                PalletAssignmentDetail::whereIn('id', $rows->pluck('id'))->delete();

                $assignment->total_carton = max(0, (int) $assignment->total_carton - $qty);

                if ($assignment->total_carton <= 0) {
                    $assignment->delete();
                } else {
                    $assignment->save();
                }

                $clearedCartons += $qty;

                if ($code) {
                    $codes[] = $code;
                }
            }

            $codes = array_values(array_unique($codes));

            return [
                'success' => true,
                'message' => $clearedCartons > 0
                    ? 'Karton ditarik dari pallet: ' . implode(', ', $codes) . " ({$clearedCartons} karton)."
                    : 'Tidak ada karton packing list ini yang masih berada di pallet.',
                'cleared_cartons' => $clearedCartons,
                'pallets' => $codes,
            ];
        });

        return response()->json($result);
    }

    /**
     * Helper: Tambah kolom ocf ke pallet_assignment jika belum ada (auto-migrate)
     */
    private function ensureOcfColumnExists()
    {
        static $checked = false;
        if ($checked) return;
        $checked = true;

        if (!\Illuminate\Support\Facades\Schema::hasColumn('pallet_assignment', 'ocf')) {
            \Illuminate\Support\Facades\Schema::table('pallet_assignment', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->string('ocf')->nullable()->after('country');
            });
        }
    }

    /**
     * Helper: Seed 528 posisi jika belum ada
     */
    private function ensureAddressesSeeded()
    {
        $rows = ['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'];
        // a & b: 14 posisi; c & d: 19 posisi (bertambah dari 16).
        $columns = ['a' => 14, 'b' => 14, 'c' => 19, 'd' => 19];

        $existing = PalletAddress::pluck('code')->flip();
        $now = now();
        $addresses = [];

        foreach ($rows as $row) {
            foreach ($columns as $col => $maxPosition) {
                for ($p = 1; $p <= $maxPosition; $p++) {
                    $code = sprintf('%s-%s-%02d', $row, $col, $p);

                    if ($existing->has($code)) {
                        continue;
                    }

                    $addresses[] = [
                        'row_label'   => $row,
                        'col_label'   => $col,
                        'position_no' => $p,
                        'code'        => $code,
                        'max_carton'  => 42,
                        'created_at'  => $now,
                        'updated_at'  => $now,
                    ];
                }
            }
        }

        foreach (array_chunk($addresses, 100) as $chunk) {
            PalletAddress::insert($chunk);
        }
    }
}
