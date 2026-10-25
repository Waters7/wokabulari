<?php

namespace App\Models;

use App\Enums\ExitPermitStatus;
use App\Enums\ExitPermitType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class DormExitPermit extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'user_id', 'type', 'reason', 'destination', 'companion', 'contact_phone', 'planned_departure', 'planned_return',
        'attachment_path', 'status', 'guardian_required', 'guardian_id', 'guardian_decided_at', 'guardian_note',
        'approved_by', 'decided_at', 'approval_note', 'actual_departure', 'departure_scanned_by', 'actual_return',
        'return_scanned_by', 'was_late', 'late_notified_at', 'qr_token',
    ];

    /** Token QR hanya ditampilkan ke pemilik izin di halamannya sendiri. */
    protected $hidden = ['qr_token'];

    protected function casts(): array
    {
        return [
            'type' => ExitPermitType::class,
            'status' => ExitPermitStatus::class,
            'planned_departure' => 'datetime',
            'planned_return' => 'datetime',
            'guardian_required' => 'boolean',
            'guardian_decided_at' => 'datetime',
            'decided_at' => 'datetime',
            'actual_departure' => 'datetime',
            'actual_return' => 'datetime',
            'was_late' => 'boolean',
            'late_notified_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'guardian_id', 'approved_by', 'actual_departure', 'actual_return'])->logOnlyDirty()->dontLogEmptyChanges()->useLogName('asrama');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guardian_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** Izin yang berlaku (sedang/akan keluar) pada tanggal tertentu. */
    public function scopeCovering(Builder $query, CarbonInterface $date): void
    {
        $query->where(function ($q) use ($date) {
            $q->whereIn('status', [ExitPermitStatus::Out, ExitPermitStatus::LateReturn])
                ->orWhere(fn ($q) => $q->where('status', ExitPermitStatus::Approved)
                    ->where('planned_departure', '<=', $date->copy()->endOfDay())
                    ->where('planned_return', '>=', $date->copy()->startOfDay()));
        });
    }

    public function attachmentUrl(): ?string
    {
        return $this->attachment_path ? Storage::disk('private')->temporaryUrl($this->attachment_path, now()->addMinutes(10)) : null;
    }

    public function periodLabel(): string
    {
        return tanggal_jam($this->planned_departure).' – '.($this->planned_return->isSameDay($this->planned_departure) ? jam($this->planned_return) : tanggal_jam($this->planned_return));
    }

    /** Sudah lewat rencana kembali tetapi belum dipindai masuk. */
    public function isOverdue(): bool
    {
        return $this->status->isOutside() && now()->gt($this->planned_return);
    }
}
