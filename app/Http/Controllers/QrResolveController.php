<?php

namespace App\Http\Controllers;

use App\Models\Pallet;
use App\Models\PalletAddress;

/**
 * Resolver QR.
 *
 * QR lokasi berisi kode `B-a-01`, QR pallet berisi `PLT-0001`. Prefix PLT-
 * dipakai untuk membedakan keduanya tanpa perlu tabel tambahan.
 */
class QrResolveController extends Controller
{
    public function show(string $code)
    {
        $code = trim($code);

        if ($code === '') {
            abort(404);
        }

        $isPallet = str_starts_with(strtoupper($code), Pallet::CODE_PREFIX);

        if ($isPallet) {
            $pallet = Pallet::where('code', strtoupper($code))->first();

            if ($pallet) {
                return redirect()->route('pallets.show', $pallet->id);
            }
        } else {
            $location = PalletAddress::where('code', $code)->first();

            if ($location) {
                return redirect()->route('locations.show', $location->id);
            }
        }

        abort(404);
    }
}