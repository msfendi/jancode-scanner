<?php

namespace App\Http\Controllers;

use App\Models\Pallet;
use App\Services\QrLabelService;
use Illuminate\Http\Request;

/**
 * Admin master pallet (identitas + QR).
 *
 * Fase ini belum mengatur penempatan pallet ke lokasi.
 */
class PalletMasterController extends Controller
{
    public function index()
    {
        $pallets = Pallet::query()->orderBy('id')->get();

        return view('pallets.index', [
            'pallets'  => $pallets,
            'statuses' => Pallet::STATUSES,
        ]);
    }

    public function create()
    {
        return view('pallets.create', [
            'statuses' => Pallet::STATUSES,
            'nextCode' => Pallet::nextCode(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'status'     => ['required', 'in:' . implode(',', array_keys(Pallet::STATUSES))],
            'max_carton' => ['required', 'integer', 'min:1', 'max:999'],
            'zone'       => ['nullable', 'string', 'max:20'],
            'notes'      => ['nullable', 'string', 'max:255'],
        ]);

        $pallet = Pallet::createNext($data);

        return redirect()
            ->route('pallets.show', $pallet->id)
            ->with('success', "Pallet {$pallet->code} dibuat.");
    }

    public function show($id)
    {
        $pallet = Pallet::findOrFail($id);

        return view('pallets.show', [
            'pallet'   => $pallet,
            'statuses' => Pallet::STATUSES,
            'qr'       => app(QrLabelService::class)->dataUri($pallet->code, 320, 2),
        ]);
    }

    public function update(Request $request, $id)
    {
        $pallet = Pallet::findOrFail($id);

        $data = $request->validate([
            'status'     => ['required', 'in:' . implode(',', array_keys(Pallet::STATUSES))],
            'max_carton' => ['required', 'integer', 'min:1', 'max:999'],
            'zone'       => ['nullable', 'string', 'max:20'],
            'notes'      => ['nullable', 'string', 'max:255'],
        ]);

        $pallet->update($data);

        return back()->with('success', "Pallet {$pallet->code} diperbarui.");
    }

    /**
     * QR polos satu pallet.
     */
    public function qrSvg($id)
    {
        $pallet = Pallet::findOrFail($id);
        $svg    = app(QrLabelService::class)->svg($pallet->code, 320, 2);

        return response($svg, 200, [
            'Content-Type'        => 'image/svg+xml',
            'Content-Disposition' => 'inline; filename="qr-' . $pallet->code . '.svg"',
        ]);
    }

    /**
     * Lembar label A4 (layout: QR besar + kode di bawah).
     */
    public function labels(Request $request)
    {
        $items = $this->labelQuery($request)->get();

        if ($items->isEmpty()) {
            return back()->with('error', 'Tidak ada pallet pada rentang yang dipilih.');
        }

        $service = app(QrLabelService::class);

        foreach ($items as $item) {
            $item->qr_data_uri = $service->dataUri($item->code, 300, 1);
        }

        return view('pallets.labels', [
            'sheets' => $service->sheets($items, 'pallet'),
            'total'  => $items->count(),
        ]);
    }

    /**
     * Query label: ids[] terpilih, atau rentang from/to.
     */
    private function labelQuery(Request $request)
    {
        $query = Pallet::query()->orderBy('id');

        $ids = array_filter((array) $request->input('ids', []));

        if (! empty($ids)) {
            return $query->whereIn('id', $ids);
        }

        if ($request->filled('from')) {
            $query->where('code', '>=', (string) $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->where('code', '<=', (string) $request->input('to'));
        }

        return $query;
    }
}