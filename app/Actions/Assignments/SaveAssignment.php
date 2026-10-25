<?php

namespace App\Actions\Assignments;

use App\Actions\Assessments\SyncSourceComponent;
use App\Enums\AssignmentStatus;
use App\Enums\ComponentSource;
use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\LearningSession;
use App\Models\User;
use App\Notifications\AssignmentPublishedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Buat/ubah tugas beserta lampiran, terbitkan, dan tautkan ke komponen sesi.
 */
class SaveAssignment
{
    public function __construct(protected SyncSourceComponent $components) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<UploadedFile>  $attachments
     */
    public function save(Classroom $classroom, User $teacher, array $data, ?Assignment $assignment = null, array $attachments = []): Assignment
    {
        $session = filled($data['learning_session_id'] ?? null) ? LearningSession::find($data['learning_session_id']) : null;

        if ($session && $session->classroom_id !== $classroom->id) {
            throw new InvalidArgumentException('Sesi tidak berada di kelas yang sama.');
        }

        return DB::transaction(function () use ($classroom, $teacher, $data, $assignment, $attachments, $session) {
            $assignment ??= new Assignment(['status' => AssignmentStatus::Draft]);
            $previousSession = $assignment->learning_session_id;

            $assignment->fill([
                'classroom_id' => $classroom->id,
                'learning_session_id' => $session?->id,
                'teacher_id' => $assignment->teacher_id ?? $teacher->id,
                'title' => $data['title'],
                'instructions' => $data['instructions'],
                'submission_type' => $data['submission_type'],
                'aspect' => $data['aspect'],
                'due_at' => $data['due_at'],
                'max_score' => (float) $data['max_score'],
                'allow_late' => (bool) ($data['allow_late'] ?? false),
                'late_penalty_percent' => (int) ($data['late_penalty_percent'] ?? 0),
                'allow_resubmit' => (bool) ($data['allow_resubmit'] ?? false),
            ]);

            // Tenggat dimundurkan → pengingat boleh dikirim ulang.
            if ($assignment->isDirty('due_at')) {
                $assignment->reminded_day_before_at = null;
                $assignment->reminded_two_hours_at = null;
            }

            $assignment->save();

            foreach ($attachments as $file) {
                $path = $file->storeAs("assignments/{$assignment->id}", Str::uuid().'.'.$file->extension(), 'private');
                $assignment->attachments()->create([
                    'file_path' => $path,
                    'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
                    'mime' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
            }

            if ($previousSession && $previousSession !== $assignment->learning_session_id) {
                $this->components->remove(ComponentSource::Assignment, $assignment->id);
            }

            if ($assignment->status !== AssignmentStatus::Draft) {
                $this->syncComponent($assignment);
            }

            return $assignment;
        });
    }

    public function publish(Assignment $assignment): void
    {
        if ($assignment->status !== AssignmentStatus::Draft) {
            return;
        }

        $assignment->update(['status' => AssignmentStatus::Published, 'published_at' => now()]);
        $this->syncComponent($assignment);

        Notification::send($assignment->classroom->participants()->active()->get(), new AssignmentPublishedNotification($assignment));
    }

    public function close(Assignment $assignment): void
    {
        $assignment->update(['status' => AssignmentStatus::Closed]);
    }

    public function deleteAttachment(Assignment $assignment, int $attachmentId): void
    {
        $attachment = $assignment->attachments()->findOrFail($attachmentId);
        Storage::disk('private')->delete($attachment->file_path);
        $attachment->delete();
    }

    /** Tugas yang ditautkan ke sesi otomatis menjadi komponen penilaian sesi. */
    public function syncComponent(Assignment $assignment): void
    {
        if (! $assignment->session) {
            return;
        }

        $component = $this->components->ensure($assignment->session, ComponentSource::Assignment, $assignment->id, 'Tugas: '.Str::limit($assignment->title, 60), $assignment->aspect);

        foreach ($assignment->submissions()->whereNotNull('final_score')->get() as $submission) {
            $this->components->setScore($component, $submission->user_id, $submission->percentage());
        }
    }
}
