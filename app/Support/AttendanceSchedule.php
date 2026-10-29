<?php

namespace App\Support;

use App\Models\Batch;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Jadwal absen harian: hari yang wajib absen, jam dibuka, batas tepat waktu, dan jam ditutup.
 * Diatur per angkatan; yang kosong memakai pengaturan sistem.
 */
final class AttendanceSchedule
{
    /** Nama hari ISO (1 = Senin … 7 = Minggu). */
    public const DAYS = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];

    /**
     * @param  list<int>  $days
     */
    public function __construct(
        public readonly array $days,
        public readonly string $opensAt,
        public readonly string $lateAfter,
        public readonly ?string $closesAt,
    ) {}

    public static function forBatch(?Batch $batch): self
    {
        $days = collect($batch?->attendance_days ?: array_keys(self::DAYS))
            ->map(fn ($day) => (int) $day)->filter(fn ($day) => isset(self::DAYS[$day]))->unique()->sort()->values()->all();

        return new self(
            $days ?: array_keys(self::DAYS),
            self::time($batch?->attendance_opens_at) ?? self::time((string) setting('attendance.open_from')) ?? '05:00',
            self::time($batch?->attendance_late_after) ?? self::time((string) setting('attendance.late_after')) ?? '07:30',
            self::time($batch?->attendance_closes_at),
        );
    }

    public function isScheduledDay(CarbonInterface $date): bool
    {
        return in_array($date->dayOfWeekIso, $this->days, true);
    }

    public function opensOn(CarbonInterface $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date)->setTimeFromTimeString($this->opensAt);
    }

    public function lateAfterOn(CarbonInterface $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date)->setTimeFromTimeString($this->lateAfter);
    }

    public function closesOn(CarbonInterface $date): ?CarbonImmutable
    {
        return $this->closesAt ? CarbonImmutable::parse($date)->setTimeFromTimeString($this->closesAt) : null;
    }

    /** Mis. "Setiap hari", "Senin–Jumat", atau "Senin, Rabu, Jumat". */
    public function daysLabel(): string
    {
        if (count($this->days) === 7) {
            return 'Setiap hari';
        }

        $first = $this->days[0];
        $last = $this->days[array_key_last($this->days)];
        $consecutive = count($this->days) >= 3 && $last - $first === count($this->days) - 1;

        return $consecutive
            ? self::DAYS[$first].'–'.self::DAYS[$last]
            : implode(', ', array_map(fn ($day) => self::DAYS[$day], $this->days));
    }

    /** Mis. "05.00–09.00, tepat waktu s.d. 07.30". */
    public function hoursLabel(): string
    {
        return jam($this->opensAt).($this->closesAt ? '–'.jam($this->closesAt) : '').', tepat waktu s.d. '.jam($this->lateAfter);
    }

    /** Hari absen berikutnya setelah tanggal ini (untuk info di hari libur). */
    public function nextScheduledDay(CarbonInterface $from): CarbonImmutable
    {
        $date = CarbonImmutable::parse($from)->startOfDay();

        for ($i = 1; $i <= 7; $i++) {
            if ($this->isScheduledDay($date->addDays($i))) {
                return $date->addDays($i);
            }
        }

        return $date->addDay();
    }

    protected static function time(?string $value): ?string
    {
        return filled($value) ? substr($value, 0, 5) : null;
    }
}
