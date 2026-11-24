<?php

namespace App\Http\Controllers;

use App\Models\Pallet;
use App\Models\PalletAddress;
use Illuminate\Http\Request;

/**
 * Halaman pencarian READ-ONLY berdasaran scan QR.
 *
 * Dua endpoint terpisah supaya operator tidak perlu tahu kode yang dipindai
 * itu lokasi atau pallet — masing-masing halaman hanya menerima jenisnya
 * sendiri dan menolak dengan pesan jelas kalau salah jenis.
 *
 * Tidak ada aksi tulis di sini: hanya menampilkan.
 */
class LookupController extends Controller
{
    /** Halaman scan QR lokasi. */
    public function lokasiPage()
    {
        return view('lookup.scan', [
            'mode'   => 'lokasi',
            'judul'  => 'Cari Lokasi (Scan QR)',
            'arah'   => 'Arahkan ke QR lokasi, mis. B-a-15',
        ]);
    }

    /** Halaman scan QR pallet. */
    public function palletPage()
    {
        return view('lookup.scan', [
            'mode'   => 'pallet',
            'judul'  => 'Cari Pallet (Scan QR)',
            'arah'   => 'Arahkan ke QR pallet, mis. PLT-0042',
        ]);
    }

    /** Data satu lokasi + pallet yang berdiri di situ + isi kartonnya. */
    public function lokasi(Request $request, string $code)
    {
        $code = trim($code);

        if (str_starts_with(strtoupper($code), Pallet::CODE_PREFIX)) {
            return response()->json([
                'success' => false,
                'message' => 'Kode ' . $code . ' ini kode PALLET, bukan lokasi. Pakai menu Cari Pallet.',
            ], 422);
        }

        $lokasi = PalletAddress::where('code', $code)->first();

        if (! $lokasi) {
            return response()->json([
                'success' => false,
                'message' => 'Kode lokasi ' . $code . ' tidak terdaftar.',
            ], 422);
        }

        $pallet = Pallet::where('current_location_id', $lokasi->id)->first();

        // Isi karton: lewat pallet bila lokasi sudah berpallet, kalau belum
        // (data lama) lewat alamat lokasinya.
        $assignment = $pallet
            ? $pallet->assignment
            : $lokasi->assignment;

        $isi = $this->isiKarton($assignment);
        $totalDetail = array_sum(array_column($isi, 'qty_karton'));

        return response()->json([
            'success' => true,
            'jenis'   => 'lokasi',
            'kode'    => $lokasi->code,
            'lokasi'  => [
                'kode'       => $lokasi->code,
                'row'        => $lokasi->row_label,
                'line'       => $lokasi->col_label,
                'pos'        => $lokasi->position_no,
                'kapasitas'  => (int) $lokasi->max_carton,
                'mode_negara' => $lokasi->country_mode,
                'aktif'      => (bool) $lokasi->is_active,
            ],
            'pallet'  => $pallet ? [
                'kode'      => $pallet->code,
                'status'    => $pallet->status,
                'label'     => $pallet->status_label,
                'kapasitas' => (int) $pallet->max_carton,
                'sejak'     => $pallet->placed_at?->format('d M Y H:i'),
                'terisi'    => (int) ($pallet->assignment->total_carton ?? 0),
            ] : null,
            'peringatan'   => array_merge(
                $this->peringatanLokasi($lokasi, $pallet),
                $this->peringatanIsi($assignment, $totalDetail)
            ),
            'total_karton' => $totalDetail,
            'isi'          => $isi,
        ]);
    }

    /** Data satu pallet + lokasinya + isi kartonnya. */
    public function pallet(Request $request, string $code)
    {
        $kodeAsli = trim($code);
        $code = strtoupper($kodeAsli);

        if (! str_starts_with($code, Pallet::CODE_PREFIX)) {
            return response()->json([
                'success' => false,
                'message' => 'Kode ' . $kodeAsli . ' ini kode LOKASI, bukan pallet. Pakai menu Cari Lokasi.',
            ], 422);
        }

        $pallet = Pallet::where('code', $code)->first();

        if (! $pallet) {
            return response()->json([
                'success' => false,
                'message' => 'Kode pallet ' . $code . ' tidak terdaftar.',
            ], 422);
        }

        $lokasi = $pallet->current_location_id
            ? PalletAddress::find($pallet->current_location_id)
            : null;

        $assignment = $pallet->assignment;
        $isi = $this->isiKarton($assignment);
        $totalDetail = array_sum(array_column($isi, 'qty_karton'));

        return response()->json([
            'success' => true,
            'jenis'   => 'pallet',
            'kode'    => $pallet->code,
            'pallet'  => [
                'kode'      => $pallet->code,
                'status'    => $pallet->status,
                'label'     => $pallet->status_label,
                'kapasitas' => (int) $pallet->max_carton,
                'sejak'     => $pallet->placed_at?->format('d M Y H:i'),
                'terisi'    => (int) ($assignment->total_carton ?? 0),
            ],
            'lokasi'  => $lokasi ? [
                'kode'      => $lokasi->code,
                'row'       => $lokasi->row_label,
                'line'      => $lokasi->col_label,
                'pos'       => $lokasi->position_no,
                'kapasitas' => (int) $lokasi->max_carton,
                'aktif'     => (bool) $lokasi->is_active,
            ] : null,
            'peringatan'   => array_merge(
                $this->peringatanPallet($pallet, $lokasi),
                $this->peringatanIsi($assignment, $totalDetail)
            ),
            'total_karton' => $totalDetail,
            'isi'          => $isi,
        ]);
    }

    /**
     * Daftar karton pada sebuah assignment (boleh null).
     *
     * @return array<int, array<string, mixed>>
     */
    private function isiKarton($assignment): array
    {
        if (! $assignment) {
            return [];
        }

        return $assignment->details()
            ->with('jancode')
            ->get()
            ->map(function ($detail) {
                $j = $detail->jancode;
                $qty = (int) $detail->qty_carton;

                return [
                    'jancode'     => $j->jancode ?? '-',
                    'size'        => $j->size ?? '-',
                    'color'       => $j->color ?? '-',
                    'style'       => $j->style ?? ($assignment->style ?? '-'),
                    'negara'      => $j->country ?? ($assignment->country ?? '-'),
                    'qty_karton'  => $qty,
                    'qty_pcs'     => $qty * (int) ($j->qty_per_carton ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    /** @return array<int, string> */
    private function peringatanLokasi(PalletAddress $lokasi, ?Pallet $pallet): array
    {
        $pesan = [];

        if (! $lokasi->is_active) {
            $pesan[] = 'Lokasi ini sedang NONAKTIF. Isinya tetap ditampilkan, tetapi lokasi tidak boleh ditempati pallet baru.';
        }

        if (! $pallet) {
            $pesan[] = 'Lokasi ini belum ditempati pallet. Isi karton di bawah adalah data lama yang masih menempel ke lokasi.';
        }

        return $pesan;
    }

    /**
     * Peringatan bila angka tersimpan tidak cocok dengan rincian karton.
     *
     * Halaman ini menampilkan jumlah dari rincian (bukan angka tersimpan),
     * supaya tabel dan totalnya selalu konsisten. Selisih tetap dilaporkan
     * supaya masalah data tidak tersembunyi.
     *
     * @return array<int, string>
     */
    private function peringatanIsi($assignment, int $totalDetail): array
    {
        if (! $assignment) {
            return [];
        }

        $tersimpan = (int) $assignment->total_carton;
        $selisih = $tersimpan - $totalDetail;

        if ($selisih > 0) {
            return [
                'Angka tersimpan menyebut ' . $tersimpan . ' karton, tetapi rincian hanya berisi '
                . $totalDetail . '. Tabel di bawah memakai rincian.',
            ];
        }

        return [];
    }

    /** @return array<int, string> */
    private function peringatanPallet(Pallet $pallet, ?PalletAddress $lokasi): array
    {
        $pesan = [];

        if (in_array($pallet->status, ['maintenance', 'retired'], true)) {
            $pesan[] = 'Pallet berstatus ' . strtoupper($pallet->status) . ' — tidak boleh dipakai untuk penempatan.';
        }

        if (! $lokasi) {
            $pesan[] = 'Pallet ini belum ditempatkan di lokasi mana pun.';
        } elseif (! $lokasi->is_active) {
            $pesan[] = 'Pallet berdiri di lokasi ' . $lokasi->code . ' yang sedang NONAKTIF.';
        }

        return $pesan;
    }
}
