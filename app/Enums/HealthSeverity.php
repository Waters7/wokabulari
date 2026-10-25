<?php

namespace App\Enums;

/**
 * Tingkat keparahan laporan sakit (SPEC 3.18).
 */
enum HealthSeverity: string
{
    case Mild = 'mild';
    case Moderate = 'moderate';
    case Severe = 'severe';
    case Emergency = 'emergency';

    public function label(): string
    {
        return match ($this) {
            self::Mild => 'Ringan',
            self::Moderate => 'Sedang',
            self::Severe => 'Berat',
            self::Emergency => 'Darurat',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Mild => 'green',
            self::Moderate => 'amber',
            self::Severe => 'orange',
            self::Emergency => 'red',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }

    /** Berat atau darurat: notifikasi prioritas & WhatsApp ke wali. */
    public function isUrgent(): bool
    {
        return in_array($this, [self::Severe, self::Emergency], true);
    }
}
