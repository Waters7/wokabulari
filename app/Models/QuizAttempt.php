<?php

namespace App\Models;

use App\Enums\AttemptStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_id', 'user_id', 'started_at', 'expires_at', 'submitted_at', 'score', 'max_score', 'percentage', 'status',
        'tab_switch_count', 'ip', 'device_info', 'session_id', 'question_order', 'option_order', 'auto_submitted',
    ];

    protected $hidden = ['session_id'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'submitted_at' => 'datetime',
            'score' => 'float',
            'max_score' => 'float',
            'percentage' => 'float',
            'status' => AttemptStatus::class,
            'tab_switch_count' => 'integer',
            'question_order' => 'array',
            'option_order' => 'array',
            'auto_submitted' => 'boolean',
        ];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuizAnswer::class);
    }

    public function scopeFinished(Builder $query): void
    {
        $query->where('status', '!=', AttemptStatus::InProgress);
    }

    public function isInProgress(): bool
    {
        return $this->status === AttemptStatus::InProgress;
    }

    public function secondsLeft(): int
    {
        return (int) max(0, now()->diffInSeconds($this->expires_at, false));
    }

    public function passed(): bool
    {
        return $this->percentage !== null && $this->percentage >= $this->quiz->passing_score;
    }

    /** Skor pada skala tryout (mis. HSK 200/300). */
    public function scaledScore(): ?float
    {
        return $this->percentage === null ? null : round($this->percentage * $this->quiz->score_scale / 100, 1);
    }
}
