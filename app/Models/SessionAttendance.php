<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class SessionAttendance extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = ['learning_session_id', 'user_id', 'attendance_code_id', 'submitted_at', 'latitude', 'longitude', 'status', 'marked_by', 'note'];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
            'status' => AttendanceStatus::class,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'marked_by', 'note'])->logOnlyDirty()->dontLogEmptyChanges()->useLogName('kehadiran');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(LearningSession::class, 'learning_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function marker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}
