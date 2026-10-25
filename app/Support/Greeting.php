<?php

namespace App\Support;

/**
 * Sapaan bernuansa Manado untuk beranda dan pesan sukses.
 */
class Greeting
{
    public static function forNow(?string $name = null): string
    {
        $hour = (int) now()->format('G');

        $time = match (true) {
            $hour < 11 => 'Selamat pagi',
            $hour < 15 => 'Selamat siang',
            $hour < 18 => 'Selamat sore',
            default => 'Selamat malam',
        };

        // "Bapak Yosef Lumowa" → "Bapak Yosef" (sapaan ikut nama depan).
        $words = $name ? preg_split('/\s+/', trim($name)) : [];
        $first = $words ? (in_array(strtolower($words[0]), ['bapak', 'ibu', 'pak', 'bu', 'bpk'], true) && isset($words[1]) ? $words[0].' '.$words[1] : $words[0]) : null;

        return $first ? "{$time}, {$first}!" : "{$time}!";
    }

    public static function tagline(): string
    {
        $lines = [
            'Ngana so siap blajar?',
            'Ayo jo, torang blajar sama-sama!',
            'Satu kata baru hari ini, satu langkah ke dunia.',
            'Kaya kosakata, torang go global!',
            'Jang lupa absen deng kumpul tugas, neh!',
        ];

        return $lines[now()->dayOfYear % count($lines)];
    }
}
