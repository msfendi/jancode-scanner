<?php

namespace App\Support;

/**
 * Utilitas kode JANCODE.
 *
 * Mendukung dua format khusus:
 *  - Case mix jancode  : dua (atau lebih) jancode dalam satu karton yang sama,
 *                        disimpan sebagai "4550725146768-4550725146769".
 *  - Broken carton     : jancode yang kartonnya tidak penuh (qty pcs di bawah
 *                        ambang) diberi akhiran "B", mis. "4550725146768B".
 */
final class JancodeCode
{
    /** Akhiran penanda karton broken. */
    public const BROKEN_SUFFIX = 'B';

    /** Panjang standar satu jancode (EAN-13). */
    public const LENGTH = 13;

    /**
     * Normalisasi input: huruf besar, tanpa spasi/karakter aneh, pemisah "-"
     * dirapikan. Huruf dipertahankan agar akhiran "B" tidak hilang.
     */
    public static function normalize(string $raw): string
    {
        $code = strtoupper(trim($raw));
        $code = preg_replace('/\s+/', '', $code) ?? '';
        $code = preg_replace('/[^0-9A-Z\-]/', '', $code) ?? '';
        $code = preg_replace('/-+/', '-', $code) ?? '';

        return trim($code, '-');
    }

    /**
     * Pecah kode gabungan menjadi bagian-bagiannya.
     *
     * @return array<int, string>
     */
    public static function parts(string $code): array
    {
        $code = self::normalize($code);

        if ($code === '') {
            return [];
        }

        return array_values(array_filter(
            explode('-', $code),
            fn (string $part): bool => $part !== ''
        ));
    }

    /**
     * Satu bagian valid: 13 digit, boleh diakhiri "B" (broken).
     */
    public static function isValidPart(string $part): bool
    {
        return (bool) preg_match('/^\d{' . self::LENGTH . '}B?$/', $part);
    }

    /**
     * Kode valid: satu jancode atau gabungan beberapa jancode.
     */
    public static function isValid(string $code): bool
    {
        $parts = self::parts($code);

        if ($parts === []) {
            return false;
        }

        foreach ($parts as $part) {
            if (! self::isValidPart($part)) {
                return false;
            }
        }

        return true;
    }

    public static function isComposite(string $code): bool
    {
        return count(self::parts($code)) > 1;
    }

    public static function isBroken(string $code): bool
    {
        $parts = self::parts($code);

        if ($parts === []) {
            return false;
        }

        return str_ends_with($parts[count($parts) - 1], self::BROKEN_SUFFIX);
    }

    /**
     * Tambahkan akhiran "B" (idempotent).
     */
    public static function markBroken(string $code): string
    {
        $code = self::normalize($code);

        if ($code === '' || str_ends_with($code, self::BROKEN_SUFFIX)) {
            return $code;
        }

        return $code . self::BROKEN_SUFFIX;
    }

    /**
     * Bagian numerik (tanpa akhiran B) untuk keperluan pencocokan.
     *
     * @return array<int, string>
     */
    public static function numericParts(string $code): array
    {
        return array_map(
            fn (string $part): string => (string) preg_replace('/B$/', '', $part),
            self::parts($code)
        );
    }

    /**
     * Cocokkan barcode hasil scan dengan kode master tersimpan.
     *
     * - exact     : "4550725146768-B" == "4550725146768-B"
     * - token     : scan salah satu jancode pada kode gabungan
     * - broken    : "4550725146768B" cocok dengan master broken
     * - gabungan  : urutan bagian berbeda tetap dianggap sama
     */
    public static function matches(string $stored, string $input): bool
    {
        $stored = self::normalize($stored);
        $input  = self::normalize($input);

        if ($stored === '' || $input === '') {
            return false;
        }

        if ($stored === $input) {
            return true;
        }

        $storedParts = self::parts($stored);
        $inputParts  = self::parts($input);

        // Scan salah satu bagian dari kode gabungan.
        if (count($inputParts) === 1 && in_array($inputParts[0], $storedParts, true)) {
            return true;
        }

        // Scan kode gabungan dengan urutan berbeda.
        if (count($storedParts) > 1 && count($inputParts) > 1) {
            $a = $storedParts;
            $b = $inputParts;
            sort($a);
            sort($b);

            return $a === $b;
        }

        return false;
    }
}
