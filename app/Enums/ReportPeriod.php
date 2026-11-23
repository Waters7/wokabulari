<?php

namespace App\Enums;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Periode laporan: harian, mingguan (Senin–Minggu), bulanan, atau rentang bebas.
 * Di Pusat Laporan data dipotong sampai tanggal yang dipilih (lihat until()).
 */
enum ReportPeriod: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Range = 'range';

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Harian',
            self::Weekly => 'Mingguan',
            self::Monthly => 'Bulanan',
            self::Range => 'Rentang',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Daily => 'green',
            self::Weekly => 'sky',
            self::Monthly => 'indigo',
            self::Range => 'amber',
        };
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} awal & akhir (tanggal) */
    public function range(CarbonInterface|string $date): array
    {
        $date = CarbonImmutable::parse($date)->startOfDay();

        return match ($this) {
            self::Daily, self::Range => [$date, $date],
            self::Weekly => [$date->startOfWeek(), $date->endOfWeek()->startOfDay()],
            self::Monthly => [$date->startOfMonth(), $date->endOfMonth()->startOfDay()],
        };
    }

    public function periodLabel(CarbonInterface|string $date): string
    {
        [$from, $to] = $this->range($date);

        return match ($this) {
            self::Daily, self::Range => tanggal($from, 'l, j F Y'),
            self::Weekly => tanggal($from, 'j M').' – '.tanggal($to, 'j M Y'),
            self::Monthly => tanggal($from, 'F Y'),
        };
    }

    /** Geser ke periode sebelum/berikutnya. */
    public function shift(CarbonInterface|string $date, int $steps): CarbonImmutable
    {
        $date = CarbonImmutable::parse($date);

        return match ($this) {
            self::Daily, self::Range => $date->addDays($steps),
            self::Weekly => $date->addWeeks($steps),
            self::Monthly => $date->startOfMonth()->addMonths($steps),
        };
    }

    /**
     * Rentang data Pusat Laporan: dari awal periode sampai tanggal yang dipilih; data sesudahnya tidak diambil.
     * Mis. bulanan 6 Nov = 1–6 Nov. Rentang memakai tanggal "dari" sendiri.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function until(CarbonInterface|string $date, CarbonInterface|string|null $from = null): array
    {
        $to = CarbonImmutable::parse($date)->startOfDay();
        $start = $this === self::Range ? CarbonImmutable::parse($from ?: $to)->startOfDay() : $this->range($to)[0];

        return $start->gt($to) ? [$to, $start] : [$start, $to];
    }

    /** Judul periode untuk until(): "November 2026" bila sebulan penuh, "1 – 6 November 2026" bila dipotong. */
    public function untilLabel(CarbonInterface|string $date, CarbonInterface|string|null $from = null): string
    {
        [$start, $end] = $this->until($date, $from);

        if (in_array($this, [self::Weekly, self::Monthly], true) && $this->range($end)[1]->eq($end)) {
            return $this->periodLabel($end);
        }

        return self::rangeLabel($start, $end);
    }

    /**
     * Geser ke periode sebelum/berikutnya untuk until(): tanggal menjadi akhir periode itu,
     * atau hari ini bila periodenya sedang berjalan.
     */
    public function shiftUntil(CarbonInterface|string $date, int $steps): CarbonImmutable
    {
        $date = CarbonImmutable::parse($date)->startOfDay();
        $end = match ($this) {
            self::Weekly => $date->addWeeks($steps)->endOfWeek()->startOfDay(),
            self::Monthly => $date->startOfMonth()->addMonths($steps)->endOfMonth()->startOfDay(),
            default => $date->addDays($steps),
        };
        $today = CarbonImmutable::today();

        return $end->gt($today) && $this->range($end)[0]->lte($today) ? $today : $end;
    }

    /** "Senin, 2 November 2026", "1 – 6 November 2026", "28 Okt – 6 Nov 2026", "30 Des 2026 – 2 Jan 2027". */
    public static function rangeLabel(CarbonInterface $from, CarbonInterface $to): string
    {
        return match (true) {
            $from->isSameDay($to) => tanggal($from, 'l, j F Y'),
            $from->isSameMonth($to) => $from->format('j').' – '.tanggal($to, 'j F Y'),
            $from->isSameYear($to) => tanggal($from, 'j M').' – '.tanggal($to, 'j M Y'),
            default => tanggal($from, 'j M Y').' – '.tanggal($to, 'j M Y'),
        };
    }
}
