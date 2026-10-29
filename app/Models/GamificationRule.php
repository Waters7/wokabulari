<?php

namespace App\Models;

use App\Enums\PointCategory;
use App\Enums\PointEvent;
use App\Enums\ReviewStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Komponen poin: aturan otomatis (event sistem) atau komponen tambahan yang diberikan manual oleh pengajar/admin.
 */
class GamificationRule extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'event_key', 'name', 'category', 'description', 'points', 'max_per_day', 'is_active',
        'is_custom', 'status', 'proposed_by', 'reviewed_by', 'reviewed_at', 'review_note',
    ];

    protected $attributes = [
        'category' => 'other',
        'is_custom' => false,
        'status' => 'approved',
    ];

    protected function casts(): array
    {
        return [
            'event_key' => PointEvent::class,
            'category' => PointCategory::class,
            'status' => ReviewStatus::class,
            'points' => 'integer',
            'max_per_day' => 'integer',
            'is_active' => 'boolean',
            'is_custom' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'points', 'max_per_day', 'is_active', 'status'])->logOnlyDirty()->dontLogEmptyChanges()->useLogName('gamifikasi');
    }

    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function awards(): HasMany
    {
        return $this->hasMany(PointAward::class);
    }

    public function scopeCustom(Builder $query): void
    {
        $query->where('is_custom', true);
    }

    /** Komponen tambahan yang sudah disetujui dan aktif (bisa diberikan ke peserta). */
    public function scopeAwardable(Builder $query): void
    {
        $query->custom()->where('status', ReviewStatus::Approved)->where('is_active', true);
    }

    public function isAwardable(): bool
    {
        return $this->is_custom && $this->status === ReviewStatus::Approved && $this->is_active;
    }

    public function icon(): string
    {
        return $this->event_key?->icon() ?? $this->category->icon();
    }

    /** Mis. "+15" atau "−10". */
    public function pointsLabel(): string
    {
        return ($this->points > 0 ? '+' : ($this->points < 0 ? '−' : '')).abs($this->points);
    }
}
