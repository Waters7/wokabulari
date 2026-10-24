<?php

namespace App\Support;

class Phone
{
    /**
     * Normalisasi nomor HP Indonesia ke format 62xxxxxxxxxx (untuk WhatsApp & login wali).
     */
    public static function normalize(?string $phone): ?string
    {
        if (blank($phone)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62'.$digits;
        }

        return $digits;
    }

    /**
     * Tampilan ramah: 0812-3456-7890.
     */
    public static function display(?string $phone): string
    {
        if (blank($phone)) {
            return '–';
        }

        $local = str_starts_with($phone, '62') ? '0'.substr($phone, 2) : $phone;

        return trim(chunk_split($local, 4, '-'), '-');
    }

    /** Tautan telepon cepat. */
    public static function telLink(?string $phone): ?string
    {
        return $phone ? 'tel:+'.ltrim($phone, '+') : null;
    }
}
