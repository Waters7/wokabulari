<?php

namespace App\Models;

use App\Enums\HealthReportStatus;
use App\Enums\HealthSeverity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class HealthReport extends Model
{
    use HasFactory, LogsActivity;

    public const SYMPTOMS = [
        'fever' => 'Demam',
        'dizziness' => 'Pusing',
        'nausea' => 'Mual/muntah',
        'diarrhea' => 'Diare',
        'cough_cold' => 'Batuk/pilek',
        'pain' => 'Nyeri',
        'shortness_of_breath' => 'Sesak napas',
        'other' => 'Lainnya',
    ];

    protected $fillable = [
        'user_id', 'reported_by', 'dormitory_id', 'dorm_room_id', 'symptoms', 'description', 'started_at', 'temperature',
        'severity', 'photo_path', 'is_emergency', 'needs_special_meal', 'status', 'reported_at', 'resolved_at',
    ];

    /** Data medis tidak ikut terserialisasi ke browser. */
    protected $hidden = ['description', 'photo_path'];

    protected function casts(): array
    {
        return [
            'symptoms' => 'array',
            'description' => 'encrypted',
            'started_at' => 'datetime',
            'temperature' => 'float',
            'severity' => HealthSeverity::class,
            'status' => HealthReportStatus::class,
            'is_emergency' => 'boolean',
            'needs_special_meal' => 'boolean',
            'reported_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        // Hanya status yang dicatat; isi medis tidak masuk log.
        return LogOptions::defaults()->logOnly(['status', 'severity'])->logOnlyDirty()->dontLogEmptyChanges()->useLogName('kesehatan');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function dormitory(): BelongsTo
    {
        return $this->belongsTo(Dormitory::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(DormRoom::class, 'dorm_room_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(HealthReportAction::class)->orderBy('acted_at');
    }

    public function sickLeave(): HasOne
    {
        return $this->hasOne(LeaveRequest::class);
    }

    public function specialMeals(): HasMany
    {
        return $this->hasMany(SpecialMealRequest::class);
    }

    public function scopeOpen(Builder $query): void
    {
        $query->where('status', '!=', HealthReportStatus::Recovered);
    }

    /** @return list<string> */
    public function symptomLabels(): array
    {
        return collect($this->symptoms ?? [])->map(fn ($key) => self::SYMPTOMS[$key] ?? $key)->values()->all();
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? Storage::disk('private')->temporaryUrl($this->photo_path, now()->addMinutes(10)) : null;
    }

    public function isSelfReported(): bool
    {
        return $this->reported_by === null || $this->reported_by === $this->user_id;
    }
}
