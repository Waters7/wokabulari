<?php

namespace App\Enums;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

enum PeriodType: string
{
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Weekly => 'Mingguan',
            self::Monthly => 'Bulanan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Weekly => 'sky',
            self::Monthly => 'indigo',
        };
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    public function range(CarbonInterface|string $date): array
    {
        $date = CarbonImmutable::parse($date);

        return match ($this) {
            self::Weekly => [$date->startOfWeek(), $date->endOfWeek()->startOfDay()],
            self::Monthly => [$date->startOfMonth(), $date->endOfMonth()->startOfDay()],
        };
    }

    public function periodLabel(CarbonInterface $start): string
    {
        return match ($this) {
            self::Weekly => 'Minggu '.tanggal($start, 'j M').' – '.tanggal($start->copy()->endOfWeek()),
            self::Monthly => tanggal($start, 'F Y'),
        };
    }
}
