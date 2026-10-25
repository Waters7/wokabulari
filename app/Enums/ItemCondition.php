<?php

namespace App\Enums;

/**
 * Kondisi barang inventaris kamar.
 */
enum ItemCondition: string
{
    case Good = 'good';
    case Fair = 'fair';
    case Damaged = 'damaged';
    case Missing = 'missing';

    public function label(): string
    {
        return match ($this) {
            self::Good => 'Baik',
            self::Fair => 'Cukup',
            self::Damaged => 'Rusak',
            self::Missing => 'Hilang',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Good => 'green',
            self::Fair => 'amber',
            self::Damaged => 'red',
            self::Missing => 'zinc',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}
