<?php

namespace App\Actions\Assignments;

use App\Actions\Assessments\SyncSourceComponent;
use App\Enums\ComponentSource;
use App\Enums\SubmissionStatus;
use App\Events\AssignmentGraded;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\User;
use App\Notifications\SubmissionReviewedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Nilai pengumpulan (potongan keterlambatan otomatis) atau minta revisi. Komentar bisa berupa voice note.
 */
class GradeSubmission
{
    public function __construct(protected SyncSourceComponent $components) {}

    public function grade(AssignmentSubmission $submission, User $grader, float $score, ?string $feedback = null, ?UploadedFile $voiceNote = null): AssignmentSubmission
    {
        $assignment = $submission->assignment;

        if ($score < 0 || $score > $assignment->max_score) {
            throw new InvalidArgumentException('Nilai harus 0–'.angka($assignment->max_score).'.');
        }

        $submission->fill([
            'score' => round($score, 2),
            'final_score' => $assignment->finalScore($score, $submission->is_late),
            'feedback' => $feedback,
            'status' => SubmissionStatus::Graded,
            'graded_by' => $grader->id,
            'graded_at' => now(),
        ]);
        $this->attachVoiceNote($submission, $voiceNote);
        $submission->save();

        $this->syncComponent($assignment, $submission);

        event(new AssignmentGraded($submission));
        $submission->user->notify(new SubmissionReviewedNotification($submission));

        return $submission;
    }

    public function requestRevision(AssignmentSubmission $submission, User $grader, string $feedback, ?UploadedFile $voiceNote = null): AssignmentSubmission
    {
        if (trim($feedback) === '' && ! $voiceNote) {
            throw new InvalidArgumentException('Tuliskan apa yang perlu diperbaiki.');
        }

        $submission->fill([
            'status' => SubmissionStatus::RevisionRequested,
            'feedback' => $feedback,
            'score' => null,
            'final_score' => null,
            'graded_by' => $grader->id,
            'graded_at' => now(),
        ]);
        $this->attachVoiceNote($submission, $voiceNote);
        $submission->save();

        $this->syncComponent($submission->assignment, $submission);
        $submission->user->notify(new SubmissionReviewedNotification($submission));

        return $submission;
    }

    protected function attachVoiceNote(AssignmentSubmission $submission, ?UploadedFile $voiceNote): void
    {
        if (! $voiceNote) {
            return;
        }

        if ($submission->feedback_audio_path) {
            Storage::disk('private')->delete($submission->feedback_audio_path);
        }

        $submission->feedback_audio_path = $voiceNote->storeAs('feedback-audio/'.$submission->assignment_id, Str::uuid().'.'.($voiceNote->extension() ?: 'webm'), 'private');
    }

    protected function syncComponent(Assignment $assignment, AssignmentSubmission $submission): void
    {
        if (! $assignment->session) {
            return;
        }

        $component = $this->components->ensure($assignment->session, ComponentSource::Assignment, $assignment->id, 'Tugas: '.Str::limit($assignment->title, 60), $assignment->aspect);
        $this->components->setScore($component, $submission->user_id, $submission->percentage());
    }
}
