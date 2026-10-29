<?php

namespace App\Listeners;

use App\Enums\AttendanceStatus;
use App\Enums\PointEvent;
use App\Events\AssignmentGraded;
use App\Events\AssignmentSubmitted;
use App\Events\DailyAttendanceRecorded;
use App\Events\DormAttendanceConfirmed;
use App\Events\EvaluationSubmitted;
use App\Events\ExitPermitReturnedLate;
use App\Events\ParticipantMarkedAbsent;
use App\Events\PointsAwarded;
use App\Events\QuizCompleted;
use App\Events\RatingSubmitted;
use App\Events\SessionAttendanceRecorded;
use App\Notifications\LevelUpNotification;
use App\Services\Gamification\BadgeService;
use App\Services\Gamification\LevelService;
use App\Services\Gamification\PointService;
use App\Services\Gamification\StreakService;

/**
 * Poin, streak, level, dan lencana dari aktivitas peserta (SPEC 3.20). Semua pemberian poin idempoten.
 */
class GamificationListener
{
    public function __construct(
        protected PointService $points,
        protected StreakService $streaks,
        protected BadgeService $badges,
        protected LevelService $levels,
    ) {}

    public function handleDailyAttendance(DailyAttendanceRecorded $event): void
    {
        $attendance = $event->attendance;
        $user = $attendance->user;

        if ($attendance->status === AttendanceStatus::Present) {
            $this->points->award($user, PointEvent::DailyAttendanceOnTime, $attendance, at: $attendance->checked_in_at);
        }

        if ($attendance->isAttended()) {
            $this->streaks->touch($user, $attendance->date);
            $this->evaluateBadges($user);
        }
    }

    public function handleSessionAttendance(SessionAttendanceRecorded $event): void
    {
        $attendance = $event->attendance;

        // Hanya input kode oleh peserta sendiri (bukan penandaan manual pengajar).
        if ($attendance->attendance_code_id && $attendance->status->countsAsAttended()) {
            $this->points->award($attendance->user, PointEvent::SessionCode, $attendance, at: $attendance->submitted_at);
        }
    }

    public function handleDormAttendance(DormAttendanceConfirmed $event): void
    {
        $attendance = $event->attendance;

        // Absen mandiri dalam jendela waktu (bukan apel manual).
        if ($attendance->isSelfCheckIn()) {
            $this->points->award($attendance->user, PointEvent::DormAttendanceOnTime, $attendance, at: $attendance->checked_in_at);
        }
    }

    public function handleAssignmentSubmitted(AssignmentSubmitted $event): void
    {
        $submission = $event->submission;

        if (! $submission->is_late) {
            $this->points->award($submission->user, PointEvent::AssignmentOnTime, $submission->assignment, at: $submission->submitted_at);
        }
    }

    public function handleAssignmentGraded(AssignmentGraded $event): void
    {
        $submission = $event->submission;

        if (($submission->percentage() ?? 0) >= 85) {
            $this->points->award($submission->user, PointEvent::AssignmentHighScore, $submission->assignment);
        }
    }

    public function handleQuizCompleted(QuizCompleted $event): void
    {
        $attempt = $event->attempt;
        $percentage = (float) ($attempt->percentage ?? 0);

        // Per quiz (bukan per attempt) agar mengulang tidak menambah poin.
        $this->points->award($attempt->user, PointEvent::QuizCompleted, $attempt->quiz, (int) floor($percentage / 5), 'Quiz: '.$attempt->quiz->title, $attempt->submitted_at);

        if ($percentage >= 100) {
            $this->points->award($attempt->user, PointEvent::QuizPerfect, $attempt->quiz, description: 'Skor sempurna: '.$attempt->quiz->title, at: $attempt->submitted_at);
        }
    }

    public function handleRating(RatingSubmitted $event): void
    {
        $rating = $event->rating;

        // Sekali per minggu, berapa pun jumlah rating yang diisi.
        $this->points->award($rating->user, PointEvent::WeeklyRating, ['rating_week', (int) $rating->week_start->format('oW')], at: $rating->created_at);
    }

    public function handleEvaluationSubmitted(EvaluationSubmitted $event): void
    {
        // Sekali per survei; hanya peserta yang mendapat poin (PointService).
        $this->points->award($event->user, PointEvent::ProgramEvaluation, $event->evaluation, description: 'Survei: '.$event->evaluation->title);
    }

    public function handleAbsent(ParticipantMarkedAbsent $event): void
    {
        $this->points->award($event->participant, PointEvent::Absent, $event->session, description: 'Alpa: '.$event->session->topic);
    }

    public function handleLateReturn(ExitPermitReturnedLate $event): void
    {
        $this->points->award($event->permit->user, PointEvent::LateReturn, $event->permit);
    }

    public function handlePointsAwarded(PointsAwarded $event): void
    {
        if (PointService::$quiet) {
            return;
        }

        $transaction = $event->transaction;
        $user = $transaction->user;
        $total = $this->points->total($user);

        if ($transaction->points > 0) {
            $before = $this->levels->forPoints($total - $transaction->points);
            $after = $this->levels->forPoints($total);

            if ($after && $before?->id !== $after->id && $after->min_points > 0) {
                $user->notify(new LevelUpNotification($after));
            }
        }

        $this->evaluateBadges($user);
    }

    protected function evaluateBadges($user): void
    {
        if (! PointService::$quiet) {
            $this->badges->evaluate($user);
        }
    }
}
