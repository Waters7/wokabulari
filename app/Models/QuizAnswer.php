<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class QuizAnswer extends Model
{
    use LogsActivity;

    protected $fillable = ['quiz_attempt_id', 'question_id', 'answer', 'audio_path', 'is_correct', 'score', 'teacher_feedback', 'graded_by', 'graded_at'];

    protected function casts(): array
    {
        return [
            'answer' => 'array',
            'is_correct' => 'boolean',
            'score' => 'float',
            'graded_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        // Hanya penilaian manual pengajar yang dicatat.
        return LogOptions::defaults()->logOnly(['score', 'teacher_feedback'])->logOnlyDirty()->dontLogEmptyChanges()->useLogName('nilai');
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class, 'quiz_attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class)->withTrashed();
    }

    public function audioUrl(): ?string
    {
        return $this->audio_path ? Storage::disk('private')->temporaryUrl($this->audio_path, now()->addMinutes(30)) : null;
    }
}
