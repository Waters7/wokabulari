<?php

namespace App\Enums;

/**
 * Event pemberi/pengurang poin gamifikasi (SPEC 3.20). Nilai poin diatur di tabel gamification_rules.
 */
enum PointEvent: string
{
    case DailyAttendanceOnTime = 'daily_attendance_on_time';
    case SessionCode = 'session_code';
    case DormAttendanceOnTime = 'dorm_attendance_on_time';
    case MealReceipt = 'meal_receipt';
    case AssignmentOnTime = 'assignment_on_time';
    case AssignmentHighScore = 'assignment_high_score';
    case QuizCompleted = 'quiz_completed';
    case QuizPerfect = 'quiz_perfect';
    case WeeklyRating = 'weekly_rating';
    case ProgramEvaluation = 'program_evaluation';
    case Absent = 'absent';
    case DailyAttendanceLate = 'daily_attendance_late';
    case DormAttendanceLate = 'dorm_attendance_late';
    case LateReturn = 'late_return';
    case Manual = 'manual';
    /** Komponen tambahan dari master data, diberikan pengajar/admin. */
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::DailyAttendanceOnTime => 'Absen harian tepat waktu',
            self::SessionCode => 'Input kode sesi',
            self::DormAttendanceOnTime => 'Absen asrama tepat waktu',
            self::MealReceipt => 'Isi tanda terima konsumsi',
            self::AssignmentOnTime => 'Tugas tepat waktu',
            self::AssignmentHighScore => 'Nilai tugas ≥ 85',
            self::QuizCompleted => 'Mengerjakan quiz',
            self::QuizPerfect => 'Skor quiz sempurna',
            self::WeeklyRating => 'Isi rating mingguan',
            self::ProgramEvaluation => 'Isi evaluasi program',
            self::Absent => 'Alpa',
            self::DailyAttendanceLate => 'Terlambat absen harian',
            self::DormAttendanceLate => 'Terlambat absen asrama',
            self::LateReturn => 'Terlambat kembali ke asrama',
            self::Manual => 'Penyesuaian admin',
            self::Custom => 'Komponen tambahan',
        };
    }

    public function color(): string
    {
        return $this->defaultPoints() < 0 ? 'red' : 'green';
    }

    public function icon(): string
    {
        return match ($this) {
            self::DailyAttendanceOnTime => 'camera',
            self::SessionCode => 'qr-code',
            self::DormAttendanceOnTime => 'moon',
            self::MealReceipt => 'cake',
            self::AssignmentOnTime, self::AssignmentHighScore => 'clipboard-document-check',
            self::QuizCompleted, self::QuizPerfect => 'pencil-square',
            self::WeeklyRating => 'star',
            self::ProgramEvaluation => 'clipboard-document-list',
            self::Absent => 'x-circle',
            self::DailyAttendanceLate, self::DormAttendanceLate => 'clock',
            self::LateReturn => 'clock',
            self::Manual => 'adjustments-horizontal',
            self::Custom => 'sparkles',
        };
    }

    /** Poin bawaan (SPEC 3.20). Quiz: skor% ÷ 5 dengan batas di sini sebagai maksimum. */
    public function defaultPoints(): int
    {
        return match ($this) {
            self::DailyAttendanceOnTime => 10,
            self::SessionCode => 5,
            self::DormAttendanceOnTime => 5,
            self::MealReceipt => 2,
            self::AssignmentOnTime => 15,
            self::AssignmentHighScore => 10,
            self::QuizCompleted => 20,
            self::QuizPerfect => 10,
            self::WeeklyRating => 5,
            self::ProgramEvaluation => 20,
            self::Absent => -10,
            self::DailyAttendanceLate, self::DormAttendanceLate => -5,
            self::LateReturn => -10,
            self::Manual, self::Custom => 0,
        };
    }

    /** Kelompok di master data Komponen Poin. */
    public function category(): PointCategory
    {
        return match ($this) {
            self::DailyAttendanceOnTime, self::SessionCode => PointCategory::Attendance,
            self::DormAttendanceOnTime => PointCategory::Dorm,
            self::MealReceipt => PointCategory::Meal,
            self::AssignmentOnTime, self::AssignmentHighScore => PointCategory::Assignment,
            self::QuizCompleted, self::QuizPerfect => PointCategory::Quiz,
            self::WeeklyRating, self::ProgramEvaluation => PointCategory::Engagement,
            self::Absent, self::LateReturn, self::DailyAttendanceLate, self::DormAttendanceLate => PointCategory::Discipline,
            self::Manual, self::Custom => PointCategory::Other,
        };
    }

    /** Event yang tidak punya aturan sendiri di tabel gamification_rules. */
    public function hasRule(): bool
    {
        return ! in_array($this, [self::Manual, self::Custom], true);
    }

    /** Alpa & terlambat kembali nonaktif secara bawaan; terlambat absen harian/asrama aktif (permintaan pemilik program). */
    public function activeByDefault(): bool
    {
        return ! in_array($this, [self::Absent, self::LateReturn], true);
    }
}
