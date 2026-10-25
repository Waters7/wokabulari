<?php

namespace App\Models;

use App\Enums\SubmissionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class AssignmentSubmission extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'assignment_id', 'user_id', 'text_answer', 'submitted_at', 'is_late', 'score', 'final_score', 'feedback',
        'feedback_audio_path', 'status', 'revision_count', 'graded_by', 'graded_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'graded_at' => 'datetime',
            'is_late' => 'boolean',
            'score' => 'float',
            'final_score' => 'float',
            'status' => SubmissionStatus::class,
            'revision_count' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['score', 'final_score', 'status', 'feedback'])->logOnlyDirty()->dontLogEmptyChanges()->useLogName('nilai');
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    public function files(): HasMany
    {
        return $this->hasMany(SubmissionFile::class);
    }

    public function feedbackAudioUrl(): ?string
    {
        return $this->feedback_audio_path ? Storage::disk('private')->temporaryUrl($this->feedback_audio_path, now()->addMinutes(30)) : null;
    }

    /** Persentase nilai akhir terhadap skor maksimal (untuk komponen sesi & saran penilaian). */
    public function percentage(): ?float
    {
        if ($this->final_score === null || $this->assignment->max_score <= 0) {
            return null;
        }

        return round($this->final_score / $this->assignment->max_score * 100, 2);
    }
}
