<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Enums\LanguageAspect;
use App\Enums\PeriodType;
use App\Enums\PublishStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Penilaian mingguan/bulanan per peserta per kelas.
 */
class Assessment extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'classroom_id', 'user_id', 'teacher_id', 'period_type', 'period_start', 'period_end',
        'listening', 'speaking', 'reading', 'writing', 'vocabulary', 'grammar', 'attitude',
        'average', 'grade', 'feedback', 'visible_to_guardian', 'status', 'published_at', 'read_at',
    ];

    protected function casts(): array
    {
        return [
            'period_type' => PeriodType::class,
            'period_start' => DateOnly::class,
            'period_end' => DateOnly::class,
            'listening' => 'float',
            'speaking' => 'float',
            'reading' => 'float',
            'writing' => 'float',
            'vocabulary' => 'float',
            'grammar' => 'float',
            'attitude' => 'float',
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
            ->logOnly(['listening', 'speaking', 'reading', 'writing', 'vocabulary', 'grammar', 'attitude', 'average', 'grade', 'status'])
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

    public function scopePublished(Builder $query): void
    {
        $query->where('status', PublishStatus::Published);
    }

    /**
     * @return array<string, float|null>
     */
    public function aspectScores(): array
    {
        return collect(LanguageAspect::periodic())->mapWithKeys(fn (LanguageAspect $a) => [$a->value => $this->{$a->value}])->all();
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
