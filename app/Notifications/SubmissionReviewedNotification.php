<?php

namespace App\Notifications;

use App\Enums\SubmissionStatus;
use App\Models\AssignmentSubmission;

class SubmissionReviewedNotification extends WokabulariNotification
{
    public function __construct(public AssignmentSubmission $submission)
    {
        $revision = $submission->status === SubmissionStatus::RevisionRequested;
        $this->icon = $revision ? 'arrow-path' : 'check-badge';
        $this->level = $revision ? 'warning' : 'success';
    }

    public function title(): string
    {
        return $this->submission->status === SubmissionStatus::RevisionRequested
            ? 'Tugas perlu revisi'
            : 'Tugas sudah dinilai';
    }

    public function body(): string
    {
        $assignment = $this->submission->assignment;

        return $this->submission->status === SubmissionStatus::RevisionRequested
            ? "{$assignment->title}: pengajar meminta perbaikan. Cek catatannya, neh."
            : "{$assignment->title}: nilai ".angka($this->submission->final_score, 1).' dari '.angka($assignment->max_score).'.';
    }

    public function url(): ?string
    {
        return route('assignments.show', $this->submission->assignment_id);
    }
}
