<?php

namespace App\Http\Controllers;

use App\Models\PalletAddress;
use App\Models\PalletRowLayout;
use App\Services\QrLabelService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin master lokasi (pallet_address).
 *
 * Lokasi = slot tetap di gudang. Kode lokasi sekaligus isi QR-nya.
 */
class LocationController extends Controller
{
    /** Urutan line pada board: a, b, c. */
    private const COLUMN_ORDER_SQL = "CASE pallet_address.col_label
        WHEN 'a' THEN 1 WHEN 'b' THEN 2 WHEN 'c' THEN 3 ELSE 4 END";

    public function index()
    {
        $locations = PalletAddress::query()
            ->leftJoin('pallet_assignment as pa', 'pa.pallet_address_id', '=', 'pallet_address.id')
            ->select([
                'pallet_address.id',
                'pallet_address.code',
                'pallet_address.row_label',
                'pallet_address.col_label',
                'pallet_address.position_no',
                'pallet_address.max_carton',
                'pallet_address.country_mode',
                'pallet_address.is_active',
                'pa.style',
                'pa.country',
                'pa.ocf',
                'pa.total_carton',
            ])
            ->orderBy('pallet_address.row_label')
            ->orderByRaw(self::COLUMN_ORDER_SQL)
            ->orderBy('pallet_address.position_no')
            ->get();

        return view('locations.index', [
            'locations' => $locations,
            'rows'      => PalletAddress::query()->distinct()->orderBy('row_label')->pluck('row_label'),
            'columns'   => PalletRowLayout::LINES,
        ]);
    }

    public function show($id)
    {
        $location = PalletAddress::with([
            'assignment.details.jancode',
        ])->findOrFail($id);

        return view('locations.show', [
            'location'   => $location,
            'assignment' => $location->assignment,
            'qr'         => app(QrLabelService::class)->dataUri($location->code, 320, 2),
        ]);
    }

    public function update(Request $request, $id)
    {
        $location = PalletAddress::findOrFail($id);

        $data = $request->validate([
            'max_carton'   => ['required', 'integer', 'min:1', 'max:999'],
            'country_mode' => ['required', 'in:single,multi'],
            'is_active'    => ['nullable', 'boolean'],
        ]);

        $location->update([
            'max_carton'   => $data['max_carton'],
            'country_mode' => $data['country_mode'],
            'is_active'    => $request->boolean('is_active'),
        ]);

        return back()->with('success', "Lokasi {$location->code} diperbarui.");
    }

    /**
     * Aktifkan / nonaktifkan banyak lokasi sekaligus.
     */
    public function bulkToggle(Request $request)
    {
        $data = $request->validate([
            'ids'    => ['required', 'array', 'min:1'],
            'ids.*'  => ['integer', 'exists:pallet_address,id'],
            'active' => ['required', 'boolean'],
        ]);

        $affected = DB::transaction(function () use ($data) {
            return PalletAddress::whereIn('id', $data['ids'])
                ->update(['is_active' => (bool) $data['active']]);
        });

        $state = $data['active'] ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('success', "{$affected} lokasi {$state}.");
    }

    /**
     * QR polos satu lokasi.
     */
    public function qrSvg($id)
    {
        $location = PalletAddress::findOrFail($id);
        $svg      = app(QrLabelService::class)->svg($location->code, 320, 2);

        return response($svg, 200, [
            'Content-Type'        => 'image/svg+xml',
            'Content-Disposition' => 'inline; filename="qr-' . $location->code . '.svg"',
        ]);
    }

    /**
     * Lembar label A4 (layout: QR kiri – kode – QR kanan).
     */
    public function labels(Request $request)
    {
        $items = $this->labelQuery($request)->get();

        if ($items->isEmpty()) {
            return back()->with('error', 'Tidak ada lokasi pada rentang yang dipilih.');
        }

        $service = app(QrLabelService::class);

        foreach ($items as $item) {
            $item->qr_data_uri = $service->dataUri($item->code, 240, 1);
        }

        return view('locations.labels', [
            'sheets' => $service->sheets($items, 'location'),
            'total'  => $items->count(),
        ]);
    }

    /**
     * Query label: ids[] terpilih, atau rentang from/to + filter row/kolom.
     */
    private function labelQuery(Request $request)
    {
        $query = PalletAddress::query()
            ->orderBy('row_label')
            ->orderByRaw(self::COLUMN_ORDER_SQL)
            ->orderBy('position_no');

        $ids = array_filter((array) $request->input('ids', []));

        if (! empty($ids)) {
            return $query->whereIn('id', $ids);
        }

        if ($request->filled('row')) {
            $query->where('row_label', strtoupper((string) $request->input('row')));
        }

        if ($request->filled('col')) {
            $query->where('col_label', strtolower((string) $request->input('col')));
        }

        if (! $request->boolean('include_inactive')) {
            $query->where('is_active', true);
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