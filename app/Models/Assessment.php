<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Enums\PeriodType;
use App\Enums\PublishStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Penilaian mingguan/bulanan per peserta per kelas. Nilai per aspek ada di assessment_scores
 * (kolom lama listening … attitude tidak dipakai lagi, hanya arsip).
 */
class Assessment extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'classroom_id', 'user_id', 'teacher_id', 'period_type', 'period_start', 'period_end',
        'average', 'grade', 'feedback', 'visible_to_guardian', 'status', 'published_at', 'read_at',
    ];

    protected function casts(): array
    {
        return [
            'period_type' => PeriodType::class,
            'period_start' => DateOnly::class,
            'period_end' => DateOnly::class,
            'average' => 'float',
            'visible_to_guardian' => 'boolean',
            'status' => PublishStatus::class,
            'published_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['average', 'grade', 'status'])
            ->logOnlyDirty()->dontLogEmptyChanges()->useLogName('nilai');
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(AssessmentScore::class);
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('status', PublishStatus::Published);
    }

    /**
     * Nilai per aspek, urut sesuai master aspek.
     *
     * @return Collection<int, AssessmentScore>
     */
    public function orderedScores(): Collection
    {
        return $this->scores->loadMissing('aspect')
            ->sortBy(fn (AssessmentScore $s) => [$s->aspect->sort_order, $s->aspect->name])->values();
    }

    /** Nilai aspek dengan kode bawaan tertentu (mis. speaking), untuk kriteria lencana. */
    public function scoreFor(string $code): ?float
    {
        return $this->scores->loadMissing('aspect')->first(fn (AssessmentScore $s) => $s->aspect->code === $code)?->score;
    }

    public function periodLabel(): string
    {
        return $this->period_type->periodLabel($this->period_start);
    }

    public function isPublished(): bool
    {
        return $this->status === PublishStatus::Published;
    }
}
