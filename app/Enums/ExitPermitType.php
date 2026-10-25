<?php

namespace App\Enums;

/**
 * Jenis izin keluar asrama (SPEC 3.16).
 */
enum ExitPermitType: string
{
    case Temporary = 'temporary';
    case HomeVisit = 'home_visit';
    case Overnight = 'overnight';
    case Emergency = 'emergency';

    public function label(): string
    {
        return match ($this) {
            self::Temporary => 'Keluar sementara',
            self::HomeVisit => 'Pulang ke rumah',
            self::Overnight => 'Menginap',
            self::Emergency => 'Darurat',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Temporary => 'sky',
            self::HomeVisit => 'indigo',
            self::Overnight => 'violet',
            self::Emergency => 'red',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}
