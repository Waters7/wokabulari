<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Enums\LeaveType;
use App\Enums\ReviewStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class LeaveRequest extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = ['user_id', 'date_from', 'date_to', 'type', 'reason', 'attachment_path', 'status', 'reviewed_by', 'reviewed_at', 'review_note', 'health_report_id'];

    protected function casts(): array
    {
        return [
            'date_from' => DateOnly::class,
            'date_to' => DateOnly::class,
            'type' => LeaveType::class,
            'status' => ReviewStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'reviewed_by', 'review_note'])->logOnlyDirty()->dontLogEmptyChanges()->useLogName('izin');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopeApproved(Builder $query): void
    {
        $query->where('status', ReviewStatus::Approved);
    }

    /** Izin disetujui yang mencakup tanggal tertentu. */
    public function scopeCovering(Builder $query, CarbonInterface|string $date): void
    {
        $query->whereDate('date_from', '<=', $date)->whereDate('date_to', '>=', $date);
    }

    public function attachmentUrl(): ?string
    {
        return $this->attachment_path ? Storage::disk('private')->temporaryUrl($this->attachment_path, now()->addMinutes(10)) : null;
    }

    public function dayCount(): int
    {
        return (int) $this->date_from->diffInDays($this->date_to) + 1;
    }

    public function periodLabel(): string
    {
        return $this->date_from->isSameDay($this->date_to)
            ? tanggal($this->date_from)
            : tanggal($this->date_from, 'j M').' – '.tanggal($this->date_to);
    }
}
