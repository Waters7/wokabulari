<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use App\Enums\LanguageAspect;
use App\Enums\SubmissionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Assignment extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'classroom_id', 'learning_session_id', 'teacher_id', 'title', 'instructions', 'submission_type', 'aspect',
        'due_at', 'max_score', 'allow_late', 'late_penalty_percent', 'allow_resubmit', 'status', 'published_at',
        'reminded_day_before_at', 'reminded_two_hours_at',
    ];

    protected function casts(): array
    {
        return [
            'submission_type' => SubmissionType::class,
            'aspect' => LanguageAspect::class,
            'status' => AssignmentStatus::class,
            'due_at' => 'datetime',
            'published_at' => 'datetime',
            'reminded_day_before_at' => 'datetime',
            'reminded_two_hours_at' => 'datetime',
            'max_score' => 'float',
            'allow_late' => 'boolean',
            'allow_resubmit' => 'boolean',
            'late_penalty_percent' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['title', 'due_at', 'max_score', 'status'])->logOnlyDirty()->dontLogEmptyChanges()->useLogName('tugas');
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(LearningSession::class, 'learning_session_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(AssignmentAttachment::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    public function scopePublished(Builder $query): void
    {
        $query->whereIn('status', [AssignmentStatus::Published, AssignmentStatus::Closed]);
    }

    public function scopeForParticipant(Builder $query, User $user): void
    {
        $query->published()->whereIn('classroom_id', $user->classrooms()->pluck('classrooms.id'));
    }

    public function isOpen(): bool
    {
        return $this->status === AssignmentStatus::Published && ($this->due_at->isFuture() || $this->allow_late);
    }

    public function isOverdue(): bool
    {
        return $this->due_at->isPast();
    }

    /** Mendekati tenggat: kurang dari 48 jam lagi. */
    public function isDueSoon(): bool
    {
        return $this->due_at->isFuture() && $this->due_at->lte(now()->addHours(48));
    }

    /** Nilai akhir setelah potongan keterlambatan. */
    public function finalScore(float $score, bool $late): float
    {
        return round($late ? $score * (1 - $this->late_penalty_percent / 100) : $score, 2);
    }
}
