<?php

namespace App\Providers;

use App\Enums\SubmissionStatus;
use App\Models\AssignmentSubmission;
use App\Models\Classroom;
use App\Models\User;
use App\Services\AssessmentSuggester;
use App\Services\GradeReport;
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

        GradeReport::section('assignments', function (User $user): ?array {
            $submissions = AssignmentSubmission::query()->where('user_id', $user->id)
                ->with('assignment')->latest('submitted_at')->limit(10)->get();

            return $submissions->isEmpty() ? null : ['submissions' => $submissions];
        });
    }
}
