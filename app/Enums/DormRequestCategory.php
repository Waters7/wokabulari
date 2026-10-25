<?php

namespace App\Enums;

/**
 * Kategori keluhan/permintaan asrama (SPEC 3.17).
 */
enum DormRequestCategory: string
{
    case BrokenFacility = 'broken_facility';
    case Cleanliness = 'cleanliness';
    case Supplies = 'supplies';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::BrokenFacility => 'Fasilitas rusak',
            self::Cleanliness => 'Kebersihan',
            self::Supplies => 'Perlengkapan',
            self::Other => 'Lainnya',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::BrokenFacility => 'red',
            self::Cleanliness => 'green',
            self::Supplies => 'sky',
            self::Other => 'zinc',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}
