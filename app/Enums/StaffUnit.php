<?php

namespace App\Enums;

enum StaffUnit: string
{
    case Consumption = 'consumption';
    case Dormitory = 'dormitory';
    case Health = 'health';

    public function label(): string
    {
        return match ($this) {
            self::Consumption => 'Konsumsi',
            self::Dormitory => 'Pengelola Asrama',
            self::Health => 'Kesehatan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Consumption => 'amber',
            self::Dormitory => 'blue',
            self::Health => 'red',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $unit) => [$unit->value => $unit->label()])->all();
    }
}
