<?php

namespace App\Enums;

/**
 * Status penanganan keluhan asrama.
 */
enum DormRequestStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Baru',
            self::InProgress => 'Diproses',
            self::Resolved => 'Selesai',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'amber',
            self::InProgress => 'sky',
            self::Resolved => 'green',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}
