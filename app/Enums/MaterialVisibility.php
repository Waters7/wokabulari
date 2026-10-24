<?php

namespace App\Enums;

enum MaterialVisibility: string
{
    case Immediate = 'immediate';
    case Scheduled = 'scheduled';
    case AfterSession = 'after_session';

    public function label(): string
    {
        return match ($this) {
            self::Immediate => 'Langsung',
            self::Scheduled => 'Terjadwal',
            self::AfterSession => 'Setelah sesi selesai',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Immediate => 'green',
            self::Scheduled => 'amber',
            self::AfterSession => 'blue',
        };
    }
}
