<?php

namespace App\Enums;

enum LeaveType: string
{
    case Excused = 'excused';
    case Sick = 'sick';

    public function label(): string
    {
        return match ($this) {
            self::Excused => 'Izin',
            self::Sick => 'Sakit',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Excused => 'sky',
            self::Sick => 'violet',
        };
    }

    /** Status kehadiran yang diisi saat izin disetujui. */
    public function attendanceStatus(): AttendanceStatus
    {
        return match ($this) {
            self::Excused => AttendanceStatus::Excused,
            self::Sick => AttendanceStatus::Sick,
        };
    }
}
