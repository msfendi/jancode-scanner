<?php

namespace App\Http\Controllers;

use App\Models\ImportBatch;
use App\Models\Jancode;
use App\Models\PalletAddress;
use App\Models\PalletAssignment;
use App\Models\PalletAssignmentDetail;
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
        // Pastikan 480 alamat pallet sudah di-seed
        $this->ensureAddressesSeeded();

        $selectedRow = $request->input('row', 'all'); // 'all' atau 'B', 'C', dll

        $query = PalletAddress::with(['assignment.details.jancode'])
            ->orderBy('row_label')
            ->orderBy('col_label')
            ->orderBy('position_no');

        if ($selectedRow !== 'all' && in_array(strtoupper($selectedRow), ['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'])) {
            $query->where('row_label', strtoupper($selectedRow));
        }

        $addresses = $query->get();

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
            ->whereIn('import_batch_id', ImportBatch::active()->pluck('id'))
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

            foreach ($assignmentsInRow as $asg) {
                $rowTotalCartons += $asg->total_carton;
                foreach ($asg->details as $det) {
                    $jc = $det->jancode;
                    if ($jc) {
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

            $rowSummaries[$r] = [
                'is_locked'       => $isLocked,
                'country'         => $isLocked ? $firstAssign->country : null,
                'style'           => $isLocked ? $firstAssign->style : null,
                'ocf'             => $rowOcf,
                'total_cartons'   => $rowTotalCartons,
                'total_pcs'       => $rowTotalPcs,
                'color_breakdown' => $colorBreakdown,
            ];
        }

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
            'rowSummaries'
        ));
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
        $addresses = PalletAddress::with('assignment')->orderBy('id')->get(['id','code','row_label','col_label','position_no','max_carton']);
        $assignments = PalletAssignment::with(['details.jancode','address'])->get();
        $maxA = PalletAssignment::max('updated_at') ?: '0';
        $maxD = PalletAssignmentDetail::max('updated_at') ?: '0';
        $sum = (int) $assignments->sum('total_carton');
        $cnt = $assignments->count();
        $hash = md5($maxA . '|' . $maxD . '|' . $sum . '|' . $cnt);
        $pallets = [];
        foreach ($addresses as $a) {
            $asg = $a->assignment;
            $pallets[] = [
                'id' => $a->id,
                'code' => $a->code,
                'row' => $a->row_label,
                'col' => $a->col_label,
                'pos' => $a->position_no,
                'cartons' => $asg ? (int)$asg->total_carton : 0,
                'style' => $asg ? $asg->style : null,
                'country' => $asg ? $asg->country : null,
                'ocf' => $asg ? ($asg->ocf ?? null) : null,
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
            foreach ($inRow as $asg) {
                foreach ($asg->details as $det) {
                    $jc = $det->jancode;
                    if (!$jc) continue;
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
                $rowSummaries[$r] = [
                    'is_locked' => true,
                    'country' => $first->country,
                    'style' => $first->style,
                    'ocf' => $first->ocf ?: ($firstJc ? ($firstJc->ocf ?? null) : null),
                    'total_cartons' => $totalCartons,
                    'total_pcs' => $totalPcs,
                    'color_breakdown' => $colorBreakdown,
                ];
            } else {
                $rowSummaries[$r] = ['is_locked'=>false,'country'=>null,'style'=>null,'ocf'=>null,'total_cartons'=>0,'total_pcs'=>0,'color_breakdown'=>[]];
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

            // Otomatis tentukan jumlah karton yang ditempatkan (tanpa perlu input manual)
            $addQty = $requestedQty ? min($requestedQty, $availableScannedToPlace) : min($availableScannedToPlace, $palletSpace);

            if ($addQty <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Tidak ada kapasitas pallet yang tersedia untuk penempatan ini."
                ], 422);
            }

            // 1. ATURAN ROW LOCKING: Seluruh Row B-I harus satu Country, Style, dan OCF yang sama
            $rowActiveAssignment = PalletAssignment::with('details.jancode')
                ->whereHas('address', fn($q) => $q->where('row_label', $address->row_label))
                ->where('total_carton', '>', 0)
                ->first();

            if ($rowActiveAssignment) {
                $rowCountry = strtoupper(trim($rowActiveAssignment->country));
                $rowStyle = strtoupper(trim($rowActiveAssignment->style));
                $firstJc = $rowActiveAssignment->details->first()?->jancode;
                $rowOcf = strtoupper(trim($rowActiveAssignment->ocf ?: ($firstJc ? $firstJc->ocf : '')));

                if ($rowCountry !== $jancodeCountry || $rowStyle !== $jancodeStyle || ($rowOcf && $rowOcf !== $jancodeOcf)) {
                    return response()->json([
                        'success' => false,
                        'message' => "Pelanggaran Aturan Row: Seluruh Row {$address->row_label} sudah dikunci untuk Country [{$rowCountry}], Style [{$rowStyle}], OCF [{$rowOcf}]. Pallet pada Row {$address->row_label} tidak dapat dicampur dengan Negara/Style/OCF lain."
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
                // BR-09: Satu alamat hanya boleh berisi item dengan style dan country yang sama
                $existingCountry = strtoupper(trim($assignment->country));
                $existingStyle = strtoupper(trim($assignment->style));

                if ($existingCountry !== $jancodeCountry || $existingStyle !== $jancodeStyle) {
                    return response()->json([
                        'success' => false,
                        'message' => "Pelanggaran BR-09: Pallet {$address->code} sudah terisi Style [{$existingStyle}] / Country [{$existingCountry}]. Tidak bisa mencampur dengan Style [{$jancodeStyle}] / Country [{$jancodeCountry}]."
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
                    'qty_carton'      => $addQty,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => "Berhasil menempatkan {$addQty} karton ke Pallet {$address->code}."
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
     * Helper: Seed 480 posisi jika belum ada
     */
    private function ensureAddressesSeeded()
    {
        if (PalletAddress::count() === 0) {
            $rows = ['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'];
            $now = now();
            $addresses = [];

            foreach ($rows as $row) {
                for ($p = 1; $p <= 14; $p++) {
                    $addresses[] = [
                        'row_label'   => $row,
                        'col_label'   => 'a',
                        'position_no' => $p,
                        'code'        => sprintf('%s-a-%02d', $row, $p),
                        'max_carton'  => 42,
                        'created_at'  => $now,
                        'updated_at'  => $now,
                    ];
                }
                for ($p = 1; $p <= 14; $p++) {
                    $addresses[] = [
                        'row_label'   => $row,
                        'col_label'   => 'b',
                        'position_no' => $p,
                        'code'        => sprintf('%s-b-%02d', $row, $p),
                        'max_carton'  => 42,
                        'created_at'  => $now,
                        'updated_at'  => $now,
                    ];
                }
                for ($p = 1; $p <= 16; $p++) {
                    $addresses[] = [
                        'row_label'   => $row,
                        'col_label'   => 'c',
                        'position_no' => $p,
                        'code'        => sprintf('%s-c-%02d', $row, $p),
                        'max_carton'  => 42,
                        'created_at'  => $now,
                        'updated_at'  => $now,
                    ];
                }
                for ($p = 1; $p <= 16; $p++) {
                    $addresses[] = [
                        'row_label'   => $row,
                        'col_label'   => 'd',
                        'position_no' => $p,
                        'code'        => sprintf('%s-d-%02d', $row, $p),
                        'max_carton'  => 42,
                        'created_at'  => $now,
                        'updated_at'  => $now,
                    ];
                }
            }

            foreach (array_chunk($addresses, 100) as $chunk) {
                PalletAddress::insert($chunk);
            }
        }
    }
}
