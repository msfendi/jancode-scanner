<?php

namespace App\Services;

use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Link join Scanner untuk perangkat lain (mis. HP).
 *
 * QR berisi token bertanda tangan (HMAC APP_KEY) yang memuat session id dan
 * packing list id. Setelah user login di HP, link yang sama akan diarahkan
 * ke session scan yang sedang berjalan di perangkat desktop.
 */
class ScannerJoinService
{
    /** Masa berlaku token: 12 jam. */
    private const TTL_SECONDS = 43200;

    public function encode(int $sessionId, ?int $batchId = null): string
    {
        $payload = [
            's' => $sessionId,
            'b' => $batchId,
            'e' => time() + self::TTL_SECONDS,
        ];

        $encoded = rtrim(strtr(base64_encode((string) json_encode($payload)), '+/', '-_'), '=');

        return $encoded . '.' . $this->sign($encoded);
    }

    /**
     * @return array{s:int,b:?int,e:int}|null
     */
    public function decode(?string $token): ?array
    {
        if (! $token || ! str_contains($token, '.')) {
            return null;
        }

        [$encoded, $signature] = array_pad(explode('.', $token, 2), 2, null);

        if (! $encoded || ! $signature || ! hash_equals($this->sign($encoded), $signature)) {
            return null;
        }

        $decoded = base64_decode(strtr($encoded, '-_', '+/'), true);
        $data    = $decoded ? json_decode($decoded, true) : null;

        if (! is_array($data) || empty($data['s'])) {
            return null;
        }

        if ((int) ($data['e'] ?? 0) < time()) {
            return null;
        }

        return [
            's' => (int) $data['s'],
            'b' => ! empty($data['b']) ? (int) $data['b'] : null,
            'e' => (int) ($data['e'] ?? 0),
        ];
    }

    /**
     * QR code format SVG (ringan, tidak butuh imagick).
     */
    public function svg(string $content, int $size = 260): string
    {
        $writer = new Writer(
            new ImageRenderer(
                new RendererStyle($size, 1),
                new SvgImageBackEnd()
            )
        );

        return $writer->writeString($content);
    }

    private function sign(string $encoded): string
    {
        return hash_hmac('sha256', $encoded, (string) config('app.key'));
    }
}
