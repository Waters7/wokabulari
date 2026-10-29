<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Support\AttendanceSchedule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Batch extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'name', 'start_date', 'end_date', 'attendance_target', 'passing_score', 'is_active',
        'attendance_days', 'attendance_opens_at', 'attendance_late_after', 'attendance_closes_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => DateOnly::class,
            'end_date' => DateOnly::class,
            'attendance_target' => 'integer',
            'passing_score' => 'float',
            'is_active' => 'boolean',
            'attendance_days' => 'array',
        ];
    }

    /** Jadwal absen harian angkatan (bawaan dari pengaturan sistem). */
    public function attendanceSchedule(): AttendanceSchedule
    {
        return AttendanceSchedule::forBatch($this);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges()->useLogName('angkatan');
    }

    public function classrooms(): HasMany
    {
        return $this->hasMany(Classroom::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public static function current(): ?self
    {
        return static::query()->active()->orderByDesc('start_date')->first();
    }

    /** Jumlah hari tersisa sampai angkatan selesai. */
    public function daysRemaining(): int
    {
        return (int) max(0, now()->startOfDay()->diffInDays($this->end_date, false));
    }

    public function isRunning(): bool
    {
        return now()->betweenIncluded($this->start_date->startOfDay(), $this->end_date->endOfDay());
    }
}
