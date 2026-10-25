<?php

namespace App\Enums;

/**
 * Hubungan wali dengan peserta.
 */
enum GuardianRelationship: string
{
    case Father = 'father';
    case Mother = 'mother';
    case Guardian = 'guardian';

    public function label(): string
    {
        return match ($this) {
            self::Father => 'Ayah',
            self::Mother => 'Ibu',
            self::Guardian => 'Wali',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Father => 'sky',
            self::Mother => 'rose',
            self::Guardian => 'zinc',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}
