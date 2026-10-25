<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class SubmissionFile extends Model
{
    protected $fillable = ['assignment_submission_id', 'kind', 'file_path', 'original_name', 'mime', 'size'];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(AssignmentSubmission::class, 'assignment_submission_id');
    }

    public function url(): string
    {
        return Storage::disk('private')->temporaryUrl($this->file_path, now()->addMinutes(30));
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }

    public function isAudio(): bool
    {
        return $this->kind === 'audio' || str_starts_with((string) $this->mime, 'audio/');
    }
}
