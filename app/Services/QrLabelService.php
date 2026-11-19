<?php

namespace App\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Pembuat QR untuk label lokasi & pallet.
 *
 * QR hanya berisi kode mentah (`B-a-01`, `PLT-0001`). Teks, border, dan
 * tata letak label digambar oleh view saat dicetak — file QR tetap polos.
 */
class QrLabelService
{
    /** Ukuran sel label per tipe, dalam milimeter. */
    public const LABEL_SIZES = [
        'location' => ['width' => 90, 'height' => 90],
        'pallet'   => ['width' => 150, 'height' => 40],
    ];

    /**
     * Jumlah label per lembar A4.
     *
     * Area cetak 202 x 289 mm (margin 4 mm):
     *   lokasi : 2 kolom x 3 baris (90 x 90 mm)  = 6
     *   pallet : 1 kolom x 7 baris (150 x 40 mm) = 7
     */
    public const PER_SHEET = [
        'location' => 6,
        'pallet'   => 7,
    ];

    /**
     * QR code format SVG (ringan, tanpa imagick).
     */
    public function svg(string $content, int $size = 300, int $margin = 1): string
    {
        $writer = new Writer(
            new ImageRenderer(
                new RendererStyle($size, $margin),
                new SvgImageBackEnd()
            )
        );

        return $writer->writeString($content);
    }

    /**
     * QR sebagai data URI, dipakai langsung di atribut src.
     *
     * Menghindari ratusan request HTTP saat mencetak satu batch label.
     */
    public function dataUri(string $content, int $size = 300, int $margin = 1): string
    {
        return 'data:image/svg+xml;base64,' . base64_encode($this->svg($content, $size, $margin));
    }

    /**
     * Pecah daftar label menjadi beberapa lembar sesuai kapasitas per lembar.
     *
     * @param  iterable<int, mixed>  $items
     * @return array<int, array<int, mixed>>
     */
    public function sheets(iterable $items, string $type): array
    {
        $perSheet = self::PER_SHEET[$type] ?? 6;

        return array_chunk(is_array($items) ? $items : iterator_to_array($items), $perSheet);
    }
}