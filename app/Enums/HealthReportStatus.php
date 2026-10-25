<?php

namespace App\Enums;

/**
 * Status laporan sakit.
 */
enum HealthReportStatus: string
{
    case Reported = 'reported';
    case Handled = 'handled';
    case Monitoring = 'monitoring';
    case Referred = 'referred';
    case Recovered = 'recovered';

    public function label(): string
    {
        return match ($this) {
            self::Reported => 'Dilaporkan',
            self::Handled => 'Ditangani',
            self::Monitoring => 'Dipantau',
            self::Referred => 'Dirujuk',
            self::Recovered => 'Pulih',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Reported => 'amber',
            self::Handled => 'sky',
            self::Monitoring => 'indigo',
            self::Referred => 'orange',
            self::Recovered => 'green',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }

    public function isOpen(): bool
    {
        return $this !== self::Recovered;
    }
}
