<?php

namespace App\Enums;

use App\Models\User;

/**
 * Jenis laporan di Pusat Laporan beserta periode dan izin yang dibutuhkan.
 */
enum ReportType: string
{
    case DailyAttendance = 'daily_attendance';
    case SessionAttendance = 'session_attendance';
    case MealReceipts = 'meal_receipts';
    case MealDeliveries = 'meal_deliveries';
    case Scores = 'scores';

    public function label(): string
    {
        return match ($this) {
            self::DailyAttendance => 'Absen harian',
            self::SessionAttendance => 'Absen per sesi',
            self::MealReceipts => 'Konsumsi diterima',
            self::MealDeliveries => 'Input konsumsi',
            self::Scores => 'Rekap nilai',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::DailyAttendance => 'Siapa saja hadir, terlambat, izin, sakit, atau tidak absen.',
            self::SessionAttendance => 'Kehadiran tiap sesi kelas beserta rincian per peserta.',
            self::MealReceipts => 'Tanda terima konsumsi peserta per jadwal dan per peserta.',
            self::MealDeliveries => 'Makanan yang diinput tim konsumsi per vendor (dasar pembayaran vendor).',
            self::Scores => 'Rata-rata nilai sesi, tugas, quiz, dan penilaian periodik per peserta.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::DailyAttendance => 'camera',
            self::SessionAttendance => 'qr-code',
            self::MealReceipts => 'pencil',
            self::MealDeliveries => 'truck',
            self::Scores => 'chart-bar',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DailyAttendance, self::SessionAttendance => 'green',
            self::MealReceipts, self::MealDeliveries => 'orange',
            self::Scores => 'sky',
        };
    }

    /** @return list<ReportPeriod> */
    public function periods(): array
    {
        return $this === self::Scores
            ? [ReportPeriod::Weekly, ReportPeriod::Monthly]
            : [ReportPeriod::Daily, ReportPeriod::Weekly, ReportPeriod::Monthly];
    }

    /** Data peserta per kelas: pimpinan & admin program; data konsumsi: juga tim konsumsi. */
    public function allows(User $user): bool
    {
        return match ($this) {
            self::MealReceipts, self::MealDeliveries => $user->can('meal.monitor') || $user->can('attendance.view-all'),
            default => $user->can('attendance.view-all'),
        };
    }

    /** @return list<self> */
    public static function for(User $user): array
    {
        return array_values(array_filter(self::cases(), fn (self $type) => $type->allows($user)));
    }
}
