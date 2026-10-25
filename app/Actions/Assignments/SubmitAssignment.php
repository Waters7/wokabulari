<?php

namespace App\Actions\Assignments;

use App\Enums\AssignmentStatus;
use App\Enums\SubmissionStatus;
use App\Events\AssignmentSubmitted;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\User;
use App\Services\ImageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Peserta mengumpulkan tugas dari HP: teks, berkas, foto, dan/atau rekaman suara.
 */
class SubmitAssignment
{
    public function __construct(protected ImageService $images) {}

    /**
     * @param  list<UploadedFile>  $files
     * @param  list<UploadedFile>  $photos
     */
    public function handle(Assignment $assignment, User $user, ?string $text, array $files = [], array $photos = [], ?UploadedFile $audio = null): AssignmentSubmission
    {
        if ($assignment->status !== AssignmentStatus::Published) {
            throw new InvalidArgumentException('Tugas ini tidak menerima pengumpulan.');
        }

        if (! $assignment->classroom->participants()->whereKey($user->id)->exists()) {
            throw new InvalidArgumentException('Ngana tidak terdaftar di kelas tugas ini.');
        }

        $late = $assignment->due_at->isPast();
        if ($late && ! $assignment->allow_late) {
            throw new InvalidArgumentException('Tenggat sudah lewat dan tugas ini tidak menerima pengumpulan terlambat.');
        }

        $existing = AssignmentSubmission::query()->where('assignment_id', $assignment->id)->where('user_id', $user->id)->first();

        if ($existing && $existing->status !== SubmissionStatus::RevisionRequested) {
            if (! $assignment->allow_resubmit) {
                throw new InvalidArgumentException('Tugas sudah dikumpulkan dan tidak bisa dikumpul ulang.');
            }

            if ($existing->status === SubmissionStatus::Graded) {
                throw new InvalidArgumentException('Tugas sudah dinilai. Minta revisi ke pengajar bila ingin memperbaiki.');
            }
        }

        $type = $assignment->submission_type;
        $text = filled($text) ? trim($text) : null;

        if (! $text && $files === [] && $photos === [] && ! $audio) {
            throw new InvalidArgumentException('Isi jawaban atau lampirkan berkas terlebih dahulu.');
        }

        if (($text && ! $type->allowsText()) || ($files && ! $type->allowsFiles()) || ($photos && ! $type->allowsPhotos()) || ($audio && ! $type->allowsAudio())) {
            throw new InvalidArgumentException('Jenis pengumpulan tidak sesuai: '.$type->label().'.');
        }

        $submission = DB::transaction(function () use ($assignment, $user, $existing, $text, $files, $photos, $audio, $late) {
            $submission = $existing ?? new AssignmentSubmission(['assignment_id' => $assignment->id, 'user_id' => $user->id]);
            $isRevision = $existing?->status === SubmissionStatus::RevisionRequested;

            // Kumpul ulang mengganti berkas lama.
            if ($existing) {
                foreach ($existing->files as $old) {
                    Storage::disk('private')->delete($old->file_path);
                }
                $existing->files()->delete();
            }

            $submission->fill([
                'text_answer' => $text,
                'submitted_at' => now(),
                'is_late' => $late,
                'status' => $late ? SubmissionStatus::Late : SubmissionStatus::Submitted,
                'score' => null,
                'final_score' => null,
                'graded_by' => null,
                'graded_at' => null,
            ]);
            $submission->revision_count = ($submission->revision_count ?? 0) + ($isRevision ? 1 : 0);
            $submission->save();

            $directory = "submissions/{$assignment->id}/{$user->id}";

            foreach ($files as $file) {
                $this->storeFile($submission, $file, $directory, 'file');
            }

            foreach ($photos as $photo) {
                $path = $this->images->storeUpload($photo, $directory, 1800, 82);
                $submission->files()->create(['kind' => 'photo', 'file_path' => $path, 'original_name' => $photo->getClientOriginalName(), 'mime' => 'image/jpeg', 'size' => Storage::disk('private')->size($path)]);
            }

            if ($audio) {
                $this->storeFile($submission, $audio, $directory, 'audio');
            }

            return $submission;
        });

        event(new AssignmentSubmitted($submission));

        return $submission->load('files');
    }

    protected function storeFile(AssignmentSubmission $submission, UploadedFile $file, string $directory, string $kind): void
    {
        $extension = $file->extension() ?: ($kind === 'audio' ? 'webm' : 'bin');
        $path = $file->storeAs($directory, Str::uuid().'.'.$extension, 'private');

        $submission->files()->create([
            'kind' => $kind,
            'file_path' => $path,
            'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);
    }
}
