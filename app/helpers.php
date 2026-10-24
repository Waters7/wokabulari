<?php

use App\Services\SettingService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

if (! function_exists('tanggal')) {
    /**
     * Format tanggal singkat Indonesia, contoh: 24 Okt 2026.
     */
    function tanggal(CarbonInterface|string|null $date, string $format = 'j M Y'): string
    {
        if (blank($date)) {
            return '–';
        }

        $date = $date instanceof CarbonInterface ? $date : Carbon::parse($date);

        return $date->locale('id')->translatedFormat($format);
    }
}

if (! function_exists('tanggal_panjang')) {
    /**
     * Format tanggal lengkap Indonesia, contoh: Sabtu, 24 Oktober 2026.
     */
    function tanggal_panjang(CarbonInterface|string|null $date): string
    {
        return tanggal($date, 'l, j F Y');
    }
}

if (! function_exists('tanggal_jam')) {
    /**
     * Format tanggal + jam WITA, contoh: 24 Okt 2026 07.45.
     */
    function tanggal_jam(CarbonInterface|string|null $date): string
    {
        return tanggal($date, 'j M Y H.i');
    }
}

if (! function_exists('jam')) {
    /**
     * Format jam Indonesia, contoh: 07.45.
     */
    function jam(CarbonInterface|string|null $time): string
    {
        if (blank($time)) {
            return '–';
        }

        return ($time instanceof CarbonInterface ? $time : Carbon::parse($time))->format('H.i');
    }
}

if (! function_exists('angka')) {
    /**
     * Format angka Indonesia (titik ribuan, koma desimal).
     */
    function angka(int|float|null $value, int $decimals = 0): string
    {
        return number_format((float) $value, $decimals, ',', '.');
    }
}

if (! function_exists('setting')) {
    /**
     * Ambil nilai pengaturan sistem (tabel settings) dengan nilai bawaan dari config/wokabulari.php.
     */
    function setting(string $key, mixed $default = null): mixed
    {
        return app(SettingService::class)->get($key, $default);
    }
}
