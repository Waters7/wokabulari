<?php

namespace App\Enums;

/**
 * Kelompok komponen poin di master data (otomatis maupun tambahan).
 */
enum PointCategory: string
{
    case Attendance = 'attendance';
    case Meal = 'meal';
    case Dorm = 'dorm';
    case Assignment = 'assignment';
    case Quiz = 'quiz';
    case Engagement = 'engagement';
    case Discipline = 'discipline';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Attendance => 'Kehadiran',
            self::Meal => 'Konsumsi',
            self::Dorm => 'Asrama',
            self::Assignment => 'Tugas',
            self::Quiz => 'Quiz',
            self::Engagement => 'Keaktifan & partisipasi',
            self::Discipline => 'Disiplin',
            self::Other => 'Lainnya',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Attendance => 'green',
            self::Meal => 'orange',
            self::Dorm => 'indigo',
            self::Assignment => 'sky',
            self::Quiz => 'violet',
            self::Engagement => 'teal',
            self::Discipline => 'red',
            self::Other => 'zinc',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Attendance => 'calendar-days',
            self::Meal => 'cake',
            self::Dorm => 'moon',
            self::Assignment => 'clipboard-document-check',
            self::Quiz => 'pencil-square',
            self::Engagement => 'hand-raised',
            self::Discipline => 'shield-exclamation',
            self::Other => 'sparkles',
        };
    }

    /**
     * Kategori yang bisa dipakai untuk komponen tambahan (diberikan manual oleh pengajar/admin).
     *
     * @return list<self>
     */
    public static function forCustom(): array
    {
        return [self::Assignment, self::Quiz, self::Engagement, self::Attendance, self::Dorm, self::Other];
    }
}
