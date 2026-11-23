<?php

namespace App\Services;

use App\Models\Pallet;
use App\Models\PalletAddress;
use App\Models\PalletAssignment;
use App\Models\PalletPlacement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Satu-satunya jalur tulis untuk hubungan pallet ↔ lokasi.
 *
 * Aturan:
 *   - 1 lokasi maksimal 1 pallet (dijaga UNIQUE current_location_id)
 *   - 1 pallet hanya di 1 lokasi
 *   - penempatan/pemindahan selalu divalidasi dengan scan 2 QR:
 *     kode lokasi dan kode pallet harus cocok dengan yang dimaksud
 *   - tidak ada "lepas"; keluar dari lokasi hanya lewat finish
 *     (isi pallet dikosongkan, lokasi menjadi kosong)
 */
class PalletPlacementService
{
    /**
     * Cari lokasi & pallet dari kode hasil scan.
     *
     * @return array{0:PalletAddress,1:Pallet}
     */
    public function resolvePair(string $locationCode, string $palletCode): array
    {
        $location = PalletAddress::where('code', trim($locationCode))->first();
        $pallet   = Pallet::where('code', strtoupper(trim($palletCode)))->first();

        if (! $location) {
            throw ValidationException::withMessages([
                'location_code' => 'QR lokasi ' . $locationCode . ' tidak terdaftar.',
            ]);
        }

        if (! $pallet) {
            throw ValidationException::withMessages([
                'pallet_code' => 'QR pallet ' . $palletCode . ' tidak terdaftar.',
            ]);
        }

        return [$location, $pallet];
    }

    /**
     * Tempatkan pallet ke lokasi kosong.
     *
     * @return array{location:PalletAddress,pallet:Pallet}
     */
    public function place(string $locationCode, string $palletCode, ?int $userId = null): array
    {
        [$location, $pallet] = $this->resolvePair($locationCode, $palletCode);

        return DB::transaction(function () use ($location, $pallet, $userId) {
            $location = PalletAddress::whereKey($location->id)->lockForUpdate()->first();
            $pallet   = Pallet::whereKey($pallet->id)->lockForUpdate()->first();

            if (! $location->is_active) {
                throw ValidationException::withMessages([
                    'location_code' => 'Lokasi ' . $location->code . ' sedang nonaktif.',
                ]);
            }

            if (in_array($pallet->status, ['maintenance', 'retired'], true)) {
                throw ValidationException::withMessages([
                    'pallet_code' => 'Pallet ' . $pallet->code . ' berstatus ' . $pallet->status . ' dan tidak bisa ditempatkan.',
                ]);
            }

            // Sudah menempel di lokasi ini -> idempoten, anggap sukses.
            if ((int) $pallet->current_location_id === (int) $location->id) {
                return ['location' => $location, 'pallet' => $pallet, 'unchanged' => true];
            }

            if ($pallet->current_location_id) {
                $current = PalletAddress::find($pallet->current_location_id);

                throw ValidationException::withMessages([
                    'pallet_code' => 'Pallet ' . $pallet->code . ' masih berada di ' . ($current->code ?? 'lokasi lain') . '.',
                ]);
            }

            $occupant = Pallet::where('current_location_id', $location->id)->first();

            if ($occupant) {
                throw ValidationException::withMessages([
                    'location_code' => 'Lokasi ' . $location->code . ' sudah berisi pallet ' . $occupant->code . '.',
                ]);
            }

            $this->bind($location, $pallet, PalletPlacement::REASON_PLACED, $userId);
            $this->adoptExistingAssignment($location, $pallet);

            return ['location' => $location, 'pallet' => $pallet, 'unchanged' => false];
        });
    }

    /**
     * K-moving assignment lama yang menempel di lokasi ini supaya ikut punya
     * pallet. Data lama bisa sudah berisi karton sebelum identitas pallet ada.
     */
    private function adoptExistingAssignment(PalletAddress $location, Pallet $pallet): void
    {
        if (! Schema::hasTable('pallet_assignment') || ! Schema::hasColumn('pallet_assignment', 'pallet_id')) {
            return;
        }

        $assignment = PalletAssignment::where('pallet_address_id', $location->id)
            ->whereNull('pallet_id')
            ->first();

        if (! $assignment) {
            return;
        }

        $assignment->update(['pallet_id' => $pallet->id]);
    }

    /**
     * Pindahkan pallet (beserta isinya) ke lokasi lain.
     *
     * @return array{location:PalletAddress,pallet:Pallet}
     */
    public function movePallet(string $palletCode, string $locationCode, ?int $userId = null): array
    {
        return DB::transaction(function () use ($palletCode, $locationCode, $userId) {
            [$location, $pallet] = $this->resolvePair($locationCode, $palletCode);

            $location = PalletAddress::whereKey($location->id)->lockForUpdate()->first();
            $pallet   = Pallet::whereKey($pallet->id)->lockForUpdate()->first();

            $alreadyThere = $this->assertCanMove($location, $pallet);

            if ($alreadyThere) {
                return ['location' => $location, 'pallet' => $pallet, 'unchanged' => true];
            }

            $this->closeActivePlacement($pallet, PalletPlacement::REASON_MOVED, $userId);
            $this->bind($location, $pallet, PalletPlacement::REASON_MOVED, $userId, false);
            $this->adoptExistingAssignment($location, $pallet);

            return ['location' => $location, 'pallet' => $pallet, 'unchanged' => false];
        });
    }

    /**
     * Validasi rencana pindah pallet TANPA mengubah data.
     *
     * Dipakai tombol "Pindah Pallet" sebelum user menekan simpan, supaya pesan
     * kesalahan muncul saat scan, bukan setelahnya.
     *
     * @return array{location:PalletAddress,pallet:Pallet}
     */
    public function checkMove(string $palletCode, string $locationCode): array
    {
        [$location, $pallet] = $this->resolvePair($locationCode, $palletCode);

        $this->assertCanMove($location, $pallet);

        return ['location' => $location, 'pallet' => $pallet];
    }

    /**
     * Aturan pemindahan pallet.
     *
     * Berbeda dari `assertBoundPair`: di sini pallet justru harus BERADA DI
     * LOKASI LAIN, karena `location_code` adalah lokasi tujuan.
     *
     * @return bool true bila pallet memang sudah di lokasi tujuan (no-op).
     */
    private function assertCanMove(PalletAddress $location, Pallet $pallet): bool
    {
        if (! $location->is_active) {
            throw ValidationException::withMessages([
                'location_code' => 'Lokasi tujuan ' . $location->code . ' sedang nonaktif.',
            ]);
        }

        if (in_array($pallet->status, ['maintenance', 'retired'], true)) {
            throw ValidationException::withMessages([
                'pallet_code' => 'Pallet ' . $pallet->code . ' berstatus ' . $pallet->status . ' dan tidak bisa dipindah.',
            ]);
        }

        if (! $pallet->current_location_id) {
            throw ValidationException::withMessages([
                'pallet_code' => 'Pallet ' . $pallet->code . ' belum ditempatkan. Gunakan Tempatkan Pallet.',
            ]);
        }

        if ((int) $pallet->current_location_id === (int) $location->id) {
            return true;
        }

        $occupant = Pallet::where('current_location_id', $location->id)->first();

        if ($occupant) {
            throw ValidationException::withMessages([
                'location_code' => 'Lokasi tujuan ' . $location->code . ' sudah berisi pallet ' . $occupant->code . '.',
            ]);
        }

        return false;
    }

    /**
     * Tukar pallet di lokasi yang SAMA.
     *
     * Berbeda dari `movePallet` yang memindahkan pallet beserta isinya:
     * di sini lokasi tidak berubah, yang berpindah adalah isi kartonnya.
     * Pallet lama keluar (jadi available), pallet baru menggantikannya.
     *
     * Berguna saat pallet fisiknya harus ditukar (rusak/kotor/dipakai di tempat
     * lain) tetapi kartonnya tetap berada di slot yang sama.
     *
     * @return array{location:PalletAddress,from:Pallet,to:Pallet,cartons:int}
     */
    public function swapPallet(string $oldPalletCode, string $newPalletCode, ?string $expectedLocationCode = null, ?int $userId = null): array
    {
        return DB::transaction(function () use ($oldPalletCode, $newPalletCode, $expectedLocationCode, $userId) {
            $old = Pallet::where('code', strtoupper(trim($oldPalletCode)))->first();
            $new = Pallet::where('code', strtoupper(trim($newPalletCode)))->first();

            if (! $old) {
                throw ValidationException::withMessages([
                    'old_pallet_code' => 'QR pallet lama ' . $oldPalletCode . ' tidak terdaftar.',
                ]);
            }

            if (! $new) {
                throw ValidationException::withMessages([
                    'new_pallet_code' => 'QR pallet baru ' . $newPalletCode . ' tidak terdaftar.',
                ]);
            }

            $old = Pallet::whereKey($old->id)->lockForUpdate()->first();
            $new = Pallet::whereKey($new->id)->lockForUpdate()->first();

            $location = $this->assertCanSwap($old, $new, $expectedLocationCode);

            // Isi karton pindah ke pallet baru. pallet_assignment.pallet_id UNIQUE,
            // jadi pallet baru dipastikan belum punya baris isi.
            $assignment = PalletAssignment::where('pallet_id', $old->id)->first();
            $cartons = $assignment ? (int) $assignment->total_carton : 0;

            if ($assignment) {
                $assignment->update([
                    'pallet_id'         => $new->id,
                    'pallet_address_id' => $location->id,
                ]);
            }

            $now = now();

            // URUTAN PENTING: `pallets.current_location_id` punya UNIQUE index,
            // jadi pallet lama harus dilepas dari lokasi LEBIH DULU sebelum
            // pallet baru menempati lokasi yang sama.
            $old->update([
                'current_location_id' => null,
                'placed_at'           => null,
                'status'              => 'available',
            ]);

            $new->update([
                'current_location_id' => $location->id,
                'placed_at'           => $now,
                'status'              => 'in_use',
            ]);

            $this->closeActivePlacement($old, PalletPlacement::REASON_SWAPPED, $userId);

            DB::table('pallet_placements')->insert([
                'pallet_id'   => $new->id,
                'location_id' => $location->id,
                'placed_at'   => $now,
                'removed_at'  => null,
                'placed_by'   => $userId,
                'removed_by'  => null,
                'reason'      => PalletPlacement::REASON_SWAPPED,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);

            return ['location' => $location, 'from' => $old, 'to' => $new, 'cartons' => $cartons];
        });
    }

    /**
     * Validasi rencana tukar pallet tanpa mengubah data.
     *
     * @return array{location:PalletAddress,from:Pallet,to:Pallet,cartons:int}
     */
    public function checkSwap(string $oldPalletCode, string $newPalletCode, ?string $expectedLocationCode = null): array
    {
        $old = Pallet::where('code', strtoupper(trim($oldPalletCode)))->first();
        $new = Pallet::where('code', strtoupper(trim($newPalletCode)))->first();

        if (! $old) {
            throw ValidationException::withMessages([
                'old_pallet_code' => 'QR pallet lama ' . $oldPalletCode . ' tidak terdaftar.',
            ]);
        }

        if (! $new) {
            throw ValidationException::withMessages([
                'new_pallet_code' => 'QR pallet baru ' . $newPalletCode . ' tidak terdaftar.',
            ]);
        }

        $location = $this->assertCanSwap($old, $new, $expectedLocationCode);

        return [
            'location' => $location,
            'from'     => $old,
            'to'       => $new,
            'cartons'  => (int) ($old->assignment->total_carton ?? 0),
        ];
    }

    /**
     * Aturan tukar pallet.
     */
    private function assertCanSwap(Pallet $old, Pallet $new, ?string $expectedLocationCode = null): PalletAddress
    {
        if ((int) $old->id === (int) $new->id) {
            throw ValidationException::withMessages([
                'new_pallet_code' => 'Pallet baru tidak boleh sama dengan pallet lama.',
            ]);
        }

        if (! $old->current_location_id) {
            throw ValidationException::withMessages([
                'old_pallet_code' => 'Pallet ' . $old->code . ' belum ditempatkan di lokasi mana pun.',
            ]);
        }

        $location = PalletAddress::find($old->current_location_id);

        if (! $location) {
            throw ValidationException::withMessages([
                'old_pallet_code' => 'Lokasi pallet ' . $old->code . ' tidak ditemukan.',
            ]);
        }

        if ($expectedLocationCode
            && strcasecmp(trim($expectedLocationCode), trim($location->code)) !== 0) {
            throw ValidationException::withMessages([
                'old_pallet_code' => 'Pallet ' . $old->code . ' berada di ' . $location->code
                    . ', bukan di area yang sedang dibuka (' . $expectedLocationCode . ').',
            ]);
        }

        if (in_array($new->status, ['maintenance', 'retired'], true)) {
            throw ValidationException::withMessages([
                'new_pallet_code' => 'Pallet ' . $new->code . ' berstatus ' . $new->status . ' dan tidak bisa dipakai.',
            ]);
        }

        if ($new->current_location_id) {
            $other = PalletAddress::find($new->current_location_id);

            throw ValidationException::withMessages([
                'new_pallet_code' => 'Pallet ' . $new->code . ' masih berada di ' . ($other->code ?? 'lokasi lain')
                    . '. Kosongkan lokasinya dulu (Finish) sebelum dipakai menukar.',
            ]);
        }

        if (PalletAssignment::where('pallet_id', $new->id)->exists()) {
            throw ValidationException::withMessages([
                'new_pallet_code' => 'Pallet ' . $new->code . ' sudah punya isi karton.',
            ]);
        }

        return $location;
    }

    /**
     * Pastikan pasangan kode lokasi + pallet benar-benar satu kesatuan.
     * Dipakai sebelum pindah karton.
     *
     * @return array{0:PalletAddress,1:Pallet}
     */
    public function assertBoundPair(string $locationCode, string $palletCode): array
    {
        [$location, $pallet] = $this->resolvePair($locationCode, $palletCode);

        if (! $pallet->current_location_id) {
            throw ValidationException::withMessages([
                'pallet_code' => 'Pallet ' . $pallet->code . ' belum ditempatkan di lokasi mana pun.',
            ]);
        }

        if ((int) $pallet->current_location_id !== (int) $location->id) {
            $current = PalletAddress::find($pallet->current_location_id);

            throw ValidationException::withMessages([
                'location_code' => 'Pallet ' . $pallet->code . ' sebenarnya berada di ' . ($current->code ?? 'lokasi lain') . ', bukan di ' . $location->code . '.',
            ]);
        }

        return [$location, $pallet];
    }

    /**
     * Kosongkan isi pallet, pallet tetap berdiri di lokasinya.
     */
    public function emptyContents(Pallet $pallet): int
    {
        return DB::transaction(function () use ($pallet) {
            $assignment = PalletAssignment::where('pallet_id', $pallet->id)->first();

            if (! $assignment) {
                return 0;
            }

            $removed = $assignment->details()->count();
            $assignment->details()->delete();
            $assignment->update(['total_carton' => 0]);

            return $removed;
        });
    }

    /**
     * Finish: kosongkan isi pallet sekaligus melepasnya dari lokasi.
     *
     * @return array{removed:int, location:?PalletAddress}
     */
    public function finish(Pallet $pallet, ?int $userId = null): array
    {
        return DB::transaction(function () use ($pallet, $userId) {
            $pallet = Pallet::whereKey($pallet->id)->lockForUpdate()->first();
            $location = $pallet->current_location_id
                ? PalletAddress::find($pallet->current_location_id)
                : null;

            $removed = $this->emptyContents($pallet);

            $pallet->update([
                'status'              => 'available',
                'current_location_id' => null,
                'placed_at'           => null,
            ]);

            $this->closeActivePlacement($pallet, PalletPlacement::REASON_FINISHED, $userId);

            return ['removed' => $removed, 'location' => $location];
        });
    }

    /**
     * Tulis ikatan pallet → lokasi + audit.
     */
    private function bind(PalletAddress $location, Pallet $pallet, string $reason, ?int $userId, bool $fresh = true): void
    {
        $now = now();

        $pallet->update([
            'current_location_id' => $location->id,
            'placed_at'           => $now,
            'status'              => $pallet->status === 'available' ? 'in_use' : $pallet->status,
        ]);

        DB::table('pallet_placements')->insert([
            'pallet_id'   => $pallet->id,
            'location_id' => $location->id,
            'placed_at'   => $now,
            'removed_at'  => null,
            'placed_by'   => $userId,
            'removed_by'  => null,
            'reason'      => $reason,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        // Denormalisasi: pallet_assignment.pallet_address_id mengikuti lokasi pallet.
        PalletAssignment::where('pallet_id', $pallet->id)->update(['pallet_address_id' => $location->id]);
    }

    /**
     * Tutup baris penempatan yang masih aktif.
     */
    private function closeActivePlacement(Pallet $pallet, string $reason, ?int $userId): void
    {
        DB::table('pallet_placements')
            ->where('pallet_id', $pallet->id)
            ->whereNull('removed_at')
            ->update([
                'removed_at' => now(),
                'removed_by' => $userId,
                'reason'     => $reason,
                'updated_at' => now(),
            ]);
    }
}