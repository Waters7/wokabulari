<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class DailyAttendance extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'user_id', 'date', 'checked_in_at', 'selfie_path', 'face_distance', 'latitude', 'longitude',
        'accuracy_m', 'distance_m', 'status', 'reject_reason', 'approved_by', 'approval_note', 'attempts', 'device_info',
    ];

    protected function casts(): array
    {
        return [
            'date' => DateOnly::class,
            'checked_in_at' => 'datetime',
            'face_distance' => 'float',
            'latitude' => 'float',
            'longitude' => 'float',
            'accuracy_m' => 'float',
            'distance_m' => 'float',
            'status' => AttendanceStatus::class,
            'attempts' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'reject_reason', 'approved_by', 'approval_note'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('kehadiran');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** Signed URL berumur pendek untuk melihat selfie (disk privat). */
    public function selfieUrl(int $minutes = 5): ?string
    {
        return $this->selfie_path ? Storage::disk('private')->temporaryUrl($this->selfie_path, now()->addMinutes($minutes)) : null;
    }

    public function isAttended(): bool
    {
        return $this->status->countsAsAttended();
    }
}
