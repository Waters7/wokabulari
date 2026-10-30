<?php

namespace App\Enums;

/** Saat dokumentasi kelas diambil. */
enum SessionPhase: string
{
    case Start = 'start';
    case End = 'end';

    public function label(): string
    {
        return match ($this) {
            self::Start => 'Mulai sesi',
            self::End => 'Akhir sesi',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Start => 'green',
            self::End => 'blue',
        };
    }
}
