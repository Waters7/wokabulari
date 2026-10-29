<?php

namespace App\Enums;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Periode laporan: harian, mingguan (Senin–Minggu), bulanan.
 */
enum ReportPeriod: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Harian',
            self::Weekly => 'Mingguan',
            self::Monthly => 'Bulanan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Daily => 'green',
            self::Weekly => 'sky',
            self::Monthly => 'indigo',
        };
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} awal & akhir (tanggal) */
    public function range(CarbonInterface|string $date): array
    {
        $date = CarbonImmutable::parse($date)->startOfDay();

        return match ($this) {
            self::Daily => [$date, $date],
            self::Weekly => [$date->startOfWeek(), $date->endOfWeek()->startOfDay()],
            self::Monthly => [$date->startOfMonth(), $date->endOfMonth()->startOfDay()],
        };
    }

    public function periodLabel(CarbonInterface|string $date): string
    {
        [$from, $to] = $this->range($date);

        return match ($this) {
            self::Daily => tanggal($from, 'l, j F Y'),
            self::Weekly => tanggal($from, 'j M').' – '.tanggal($to, 'j M Y'),
            self::Monthly => tanggal($from, 'F Y'),
        };
    }

    /** Geser ke periode sebelum/berikutnya. */
    public function shift(CarbonInterface|string $date, int $steps): CarbonImmutable
    {
        $date = CarbonImmutable::parse($date);

        return match ($this) {
            self::Daily => $date->addDays($steps),
            self::Weekly => $date->addWeeks($steps),
            self::Monthly => $date->startOfMonth()->addMonths($steps),
        };
    }
}
