<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Enums\SessionStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class LearningSession extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = ['classroom_id', 'teacher_id', 'date', 'start_time', 'end_time', 'topic', 'objectives', 'status', 'started_at', 'ended_at'];

    protected function casts(): array
    {
        return [
            'date' => DateOnly::class,
            'status' => SessionStatus::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['date', 'start_time', 'end_time', 'topic', 'status'])->logOnlyDirty()->dontLogEmptyChanges()->useLogName('sesi');
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function codes(): HasMany
    {
        return $this->hasMany(SessionAttendanceCode::class);
    }

    /** Kode yang sedang berlaku (aktif & belum kedaluwarsa). */
    public function activeCode(): HasOne
    {
        return $this->hasOne(SessionAttendanceCode::class)
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->latestOfMany();
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(SessionAttendance::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(SessionMaterial::class)->orderBy('sort_order')->orderBy('id');
    }

    public function scopeForTeacher(Builder $query, User $user): void
    {
        if ($user->can('session.view-all')) {
            return;
        }

        $query->where(fn (Builder $q) => $q
            ->whereHas('classroom', fn (Builder $c) => $c->where('teacher_id', $user->id))
            ->orWhere('teacher_id', $user->id));
    }

    public function scopeOnDate(Builder $query, CarbonInterface|string $date): void
    {
        $query->whereDate('date', $date);
    }

    public function startsAt(): CarbonInterface
    {
        return $this->date->copy()->setTimeFromTimeString($this->start_time);
    }

    public function endsAt(): CarbonInterface
    {
        return $this->date->copy()->setTimeFromTimeString($this->end_time);
    }

    public function isOngoing(): bool
    {
        return $this->status === SessionStatus::Ongoing;
    }

    public function isCompleted(): bool
    {
        return $this->status === SessionStatus::Completed;
    }

    public function timeRange(): string
    {
        return jam($this->start_time).'–'.jam($this->end_time);
    }
}
