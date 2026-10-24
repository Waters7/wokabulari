<?php

namespace App\Enums;

/**
 * Status kehadiran (absen harian & kehadiran sesi).
 */
enum AttendanceStatus: string
{
    case Present = 'present';
    case Late = 'late';
    case Excused = 'excused';
    case Sick = 'sick';
    case Absent = 'absent';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Hadir',
            self::Late => 'Terlambat',
            self::Excused => 'Izin',
            self::Sick => 'Sakit',
            self::Absent => 'Alpa',
            self::Rejected => 'Ditolak',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Present => 'green',
            self::Late => 'amber',
            self::Excused => 'sky',
            self::Sick => 'violet',
            self::Absent => 'red',
            self::Rejected => 'rose',
        };
    }

    /** Dihitung hadir untuk persentase kehadiran. */
    public function countsAsAttended(): bool
    {
        return in_array($this, [self::Present, self::Late], true);
    }

    /** @return list<self> */
    public static function attended(): array
    {
        return [self::Present, self::Late];
    }

    /** Status yang boleh dipilih pengajar saat menandai manual. */
    public static function manualOptions(): array
    {
        return [self::Present, self::Late, self::Excused, self::Sick, self::Absent];
    }
}
