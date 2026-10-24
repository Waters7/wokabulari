<?php

namespace App\Enums;

enum Gender: string
{
    case Male = 'male';
    case Female = 'female';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'Laki-laki',
            self::Female => 'Perempuan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Male => 'sky',
            self::Female => 'pink',
        };
    }

    /** Label asrama: putra/putri. */
    public function dormLabel(): string
    {
        return match ($this) {
            self::Male => 'Putra',
            self::Female => 'Putri',
        };
    }

    public static function fromImport(?string $value): ?self
    {
        return match (strtoupper(trim((string) $value))) {
            'L', 'LAKI-LAKI', 'LAKI', 'PRIA', 'MALE', 'M' => self::Male,
            'P', 'PEREMPUAN', 'WANITA', 'FEMALE', 'F' => self::Female,
            default => null,
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}
