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
    case AssignmentOnTime = 'assignment_on_time';
    case AssignmentHighScore = 'assignment_high_score';
    case QuizCompleted = 'quiz_completed';
    case QuizPerfect = 'quiz_perfect';
    case WeeklyRating = 'weekly_rating';
    case ProgramEvaluation = 'program_evaluation';
    case Absent = 'absent';
    case LateReturn = 'late_return';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::DailyAttendanceOnTime => 'Absen harian tepat waktu',
            self::SessionCode => 'Input kode sesi',
            self::DormAttendanceOnTime => 'Absen asrama tepat waktu',
            self::AssignmentOnTime => 'Tugas tepat waktu',
            self::AssignmentHighScore => 'Nilai tugas ≥ 85',
            self::QuizCompleted => 'Mengerjakan quiz',
            self::QuizPerfect => 'Skor quiz sempurna',
            self::WeeklyRating => 'Isi rating mingguan',
            self::ProgramEvaluation => 'Isi evaluasi program',
            self::Absent => 'Alpa',
            self::LateReturn => 'Terlambat kembali ke asrama',
            self::Manual => 'Penyesuaian admin',
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
            self::AssignmentOnTime, self::AssignmentHighScore => 'clipboard-document-check',
            self::QuizCompleted, self::QuizPerfect => 'pencil-square',
            self::WeeklyRating => 'star',
            self::ProgramEvaluation => 'clipboard-document-list',
            self::Absent => 'x-circle',
            self::LateReturn => 'clock',
            self::Manual => 'adjustments-horizontal',
        };
    }

    /** Poin bawaan (SPEC 3.20). Quiz: skor% ÷ 5 dengan batas di sini sebagai maksimum. */
    public function defaultPoints(): int
    {
        return match ($this) {
            self::DailyAttendanceOnTime => 10,
            self::SessionCode => 5,
            self::DormAttendanceOnTime => 5,
            self::AssignmentOnTime => 15,
            self::AssignmentHighScore => 10,
            self::QuizCompleted => 20,
            self::QuizPerfect => 10,
            self::WeeklyRating => 5,
            self::ProgramEvaluation => 20,
            self::Absent => -10,
            self::LateReturn => -10,
            self::Manual => 0,
        };
    }

    /** Aturan pengurang poin nonaktif secara bawaan. */
    public function activeByDefault(): bool
    {
        return ! in_array($this, [self::Absent, self::LateReturn], true);
    }
}
