<?php

namespace App\Console\Commands;

use App\Enums\AttemptStatus;
use App\Enums\DormAttendanceStatus;
use App\Enums\Role;
use App\Events\AssignmentGraded;
use App\Events\AssignmentSubmitted;
use App\Events\DailyAttendanceRecorded;
use App\Events\DormAttendanceConfirmed;
use App\Events\QuizCompleted;
use App\Events\RatingSubmitted;
use App\Events\SessionAttendanceRecorded;
use App\Models\AssignmentSubmission;
use App\Models\DailyAttendance;
use App\Models\DormAttendance;
use App\Models\QuizAttempt;
use App\Models\SessionAttendance;
use App\Models\TeacherRating;
use App\Models\User;
use App\Models\VendorRating;
use App\Services\Gamification\BadgeService;
use App\Services\Gamification\PointService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:gamification-backfill')]
#[Description('Hitung poin, streak, dan lencana dari data aktivitas yang sudah ada (aman dijalankan ulang).')]
class GamificationBackfill extends Command
{
    public function handle(BadgeService $badges): int
    {
        PointService::$quiet = true;

        try {
            // Urut tanggal agar streak terbentuk benar. Callback tidak mengembalikan nilai: `false` akan menghentikan each().
            DailyAttendance::query()->with('user')->orderBy('date')->each(function ($a) {
                if ($a->isAttended()) {
                    event(new DailyAttendanceRecorded($a));
                }
            });
            SessionAttendance::query()->with('user')->whereNotNull('attendance_code_id')->each(function ($a) {
                event(new SessionAttendanceRecorded($a));
            });
            DormAttendance::query()->with('user')->where('status', DormAttendanceStatus::Confirmed)->each(function ($a) {
                event(new DormAttendanceConfirmed($a));
            });
            AssignmentSubmission::query()->with(['user', 'assignment'])->whereNotNull('submitted_at')->each(function ($s) {
                event(new AssignmentSubmitted($s));
                if ($s->final_score !== null) {
                    event(new AssignmentGraded($s));
                }
            });
            QuizAttempt::query()->with(['user', 'quiz'])->where('status', AttemptStatus::Graded)->orderBy('submitted_at')->each(function ($a) {
                event(new QuizCompleted($a));
            });
            VendorRating::query()->with('user')->each(function ($r) {
                event(new RatingSubmitted($r));
            });
            TeacherRating::query()->with('user')->each(function ($r) {
                event(new RatingSubmitted($r));
            });
        } finally {
            PointService::$quiet = false;
        }

        // Lencana dievaluasi sekali per peserta (bukan per transaksi).
        $count = 0;
        User::query()->role(Role::Participant->value)->each(function (User $user) use ($badges, &$count) {
            $count += $badges->evaluate($user)->count();
        });

        $this->info("Backfill selesai. {$count} lencana diberikan.");

        return self::SUCCESS;
    }
}
