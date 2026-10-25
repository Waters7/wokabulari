<?php

namespace App\Models;

use App\Enums\LanguageAspect;
use App\Enums\QuizStatus;
use App\Enums\QuizType;
use App\Enums\ReadingDisplay;
use App\Enums\ResultVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Quiz extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'classroom_id', 'teacher_id', 'learning_session_id', 'type', 'title', 'instructions', 'aspect', 'opens_at', 'closes_at',
        'duration_minutes', 'max_attempts', 'passing_score', 'shuffle_questions', 'shuffle_options', 'result_visibility',
        'results_released_at', 'reading_display', 'score_scale', 'status',
    ];

    protected function casts(): array
    {
        return [
            'type' => QuizType::class,
            'aspect' => LanguageAspect::class,
            'status' => QuizStatus::class,
            'result_visibility' => ResultVisibility::class,
            'reading_display' => ReadingDisplay::class,
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'results_released_at' => 'datetime',
            'duration_minutes' => 'integer',
            'max_attempts' => 'integer',
            'passing_score' => 'float',
            'shuffle_questions' => 'boolean',
            'shuffle_options' => 'boolean',
            'score_scale' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['title', 'type', 'opens_at', 'closes_at', 'status', 'results_released_at'])->logOnlyDirty()->dontLogEmptyChanges()->useLogName('quiz');
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(LearningSession::class, 'learning_session_id');
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'quiz_question')->withPivot(['sort_order', 'points', 'section'])->orderByPivot('sort_order');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->whereIn('status', [QuizStatus::Published, QuizStatus::Closed])
            ->whereIn('classroom_id', $user->classrooms()->pluck('classrooms.id'));
    }

    public function isOpen(): bool
    {
        return $this->status === QuizStatus::Published
            && ($this->opens_at === null || $this->opens_at->isPast())
            && ($this->closes_at === null || $this->closes_at->isFuture());
    }

    public function isClosed(): bool
    {
        return $this->status === QuizStatus::Closed || ($this->closes_at !== null && $this->closes_at->isPast());
    }

    /** Hasil (skor & pembahasan) boleh dilihat peserta. */
    public function resultsVisible(): bool
    {
        return match ($this->result_visibility) {
            ResultVisibility::Immediately => true,
            ResultVisibility::AfterClose => $this->isClosed(),
            ResultVisibility::Manual => $this->results_released_at !== null,
        };
    }

    public function pointsFor(Question $question): float
    {
        return (float) ($question->pivot?->points ?? $question->points);
    }

    public function totalPoints(): float
    {
        return (float) $this->questions->sum(fn (Question $q) => $this->pointsFor($q));
    }
}
