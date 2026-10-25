<?php

namespace App\Enums;

/**
 * Tampilan bacaan (pinyin/romaji) pada soal.
 */
enum ReadingDisplay: string
{
    case Ruby = 'ruby';
    case Hidden = 'hidden';
    case Tap = 'tap';

    public function label(): string
    {
        return match ($this) {
            self::Ruby => 'Di atas teks',
            self::Hidden => 'Disembunyikan',
            self::Tap => 'Muncul saat diketuk',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Ruby => 'green',
            self::Hidden => 'zinc',
            self::Tap => 'amber',
        };
    }
}
