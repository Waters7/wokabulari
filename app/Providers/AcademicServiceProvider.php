<?php

namespace App\Providers;

use App\Enums\AttemptStatus;
use App\Enums\SubmissionStatus;
use App\Models\AssignmentSubmission;
use App\Models\Classroom;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\AssessmentSuggester;
use App\Services\GradeReport;
use App\Services\QuizAnalytics;
use Carbon\CarbonInterface;
use Illuminate\Support\ServiceProvider;

/**
 * Menyambungkan nilai tugas & quiz ke saran penilaian periodik dan halaman nilai peserta.
 */
class AcademicServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Tugas yang tidak ditautkan ke sesi tetap ikut saran nilai periodik (tugas di sesi sudah lewat komponen).
        AssessmentSuggester::extend(function (Classroom $classroom, int $userId, CarbonInterface $from, CarbonInterface $to): array {
            return AssignmentSubmission::query()
                ->where('user_id', $userId)
                ->where('status', SubmissionStatus::Graded)
                ->whereHas('assignment', fn ($q) => $q->where('classroom_id', $classroom->id)->whereNull('learning_session_id')
                    ->whereBetween('due_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]))
                ->with('assignment')
                ->get()
                ->groupBy(fn (AssignmentSubmission $s) => $s->assignment->aspect->value)
                ->map(fn ($group) => $group->map->percentage()->filter(fn ($v) => $v !== null)->values()->all())
                ->all();
        });

        // Quiz yang tidak ditautkan ke sesi ikut saran nilai periodik (aspek quiz, nilai terbaik per quiz).
        AssessmentSuggester::extend(function (Classroom $classroom, int $userId, CarbonInterface $from, CarbonInterface $to): array {
            return QuizAttempt::query()
                ->where('user_id', $userId)
                ->where('status', AttemptStatus::Graded)
                ->whereBetween('submitted_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                ->whereHas('quiz', fn ($q) => $q->where('classroom_id', $classroom->id)->whereNull('learning_session_id'))
                ->with('quiz')
                ->get()
                ->groupBy('quiz_id')
                ->map(fn ($attempts) => ['aspect' => $attempts->first()->quiz->aspect->value, 'value' => (float) $attempts->max('percentage')])
                ->groupBy('aspect')
                ->map(fn ($group) => $group->pluck('value')->all())
                ->all();
        });

        GradeReport::section('quizzes', function (User $user): ?array {
            $attempts = QuizAttempt::query()->where('user_id', $user->id)->where('status', AttemptStatus::Graded)
                ->with('quiz')->latest('submitted_at')->limit(15)->get()
                ->filter(fn (QuizAttempt $a) => $a->quiz?->resultsVisible())->values();

            $classroom = $user->activeClassroom();
            $ngain = $classroom ? collect(app(QuizAnalytics::class)->nGain($classroom)['rows'])->firstWhere('user.id', $user->id) : null;

            if ($attempts->isEmpty() && ! $ngain) {
                return null;
            }

            return ['attempts' => $attempts, 'ngain' => ($ngain && ($ngain['pre'] !== null || $ngain['post'] !== null)) ? $ngain : null];
        });

        GradeReport::section('assignments', function (User $user): ?array {
            $submissions = AssignmentSubmission::query()->where('user_id', $user->id)
                ->with('assignment')->latest('submitted_at')->limit(10)->get();

            return $submissions->isEmpty() ? null : ['submissions' => $submissions];
        });
    }
}
