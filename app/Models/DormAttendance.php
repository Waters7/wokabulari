<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Enums\DormAttendanceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class DormAttendance extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'user_id', 'dormitory_id', 'date', 'checked_in_at', 'selfie_path', 'face_distance', 'latitude', 'longitude',
        'accuracy_m', 'distance_m', 'status', 'reject_reason', 'attempts', 'confirmed_by', 'confirmed_at', 'note',
    ];

    /** Selfie tidak ikut terserialisasi; ditampilkan lewat signed URL khusus petugas. */
    protected $hidden = ['selfie_path'];

    protected function casts(): array
    {
        return [
            'date' => DateOnly::class,
            'checked_in_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'status' => DormAttendanceStatus::class,
            'face_distance' => 'float',
            'latitude' => 'float',
            'longitude' => 'float',
            'accuracy_m' => 'float',
            'distance_m' => 'float',
            'attempts' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'confirmed_by', 'note'])->logOnlyDirty()->dontLogEmptyChanges()->useLogName('asrama');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dormitory(): BelongsTo
    {
        return $this->belongsTo(Dormitory::class);
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function selfieUrl(): ?string
    {
        return $this->selfie_path ? Storage::disk('private')->temporaryUrl($this->selfie_path, now()->addMinutes(10)) : null;
    }

    /** Absen mandiri (selfie/wajah) vs apel manual petugas. */
    public function isSelfCheckIn(): bool
    {
        return $this->checked_in_at !== null && $this->attempts > 0;
    }
}
