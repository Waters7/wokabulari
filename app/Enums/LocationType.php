<?php

namespace App\Enums;

enum LocationType: string
{
    case Classroom = 'classroom';
    case Dormitory = 'dormitory';

    public function label(): string
    {
        return match ($this) {
            self::Classroom => 'Kelas',
            self::Dormitory => 'Asrama',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Classroom => 'green',
            self::Dormitory => 'blue',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}
