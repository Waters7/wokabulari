<?php

namespace App\Models;

use App\Enums\Gender;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Dormitory extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = ['name', 'gender', 'location_id', 'capacity', 'curfew_time', 'check_window_start', 'check_window_end'];

    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'capacity' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges()->useLogName('asrama');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(DormRoom::class)->orderBy('number');
    }

    public function assignments(): HasManyThrough
    {
        return $this->hasManyThrough(DormRoomAssignment::class, DormRoom::class);
    }

    /** Penghuni aktif asrama ini. */
    public function residents()
    {
        return User::query()->whereHas('roomAssignments', function ($query) {
            $query->whereNull('check_out_date')->whereHas('room', fn ($room) => $room->where('dormitory_id', $this->id));
        });
    }

    /** Jendela absen malam pada tanggal tertentu (WITA). */
    public function checkWindow(?CarbonInterface $date = null): array
    {
        $date = ($date ?? now())->copy()->startOfDay();

        return [
            $date->copy()->setTimeFromTimeString($this->check_window_start),
            $date->copy()->setTimeFromTimeString($this->check_window_end),
        ];
    }

    /** Batas absen terlambat (pengaturan dorm.late_until), tidak lebih awal dari akhir jendela absen. */
    public function lateUntil(?CarbonInterface $date = null): CarbonInterface
    {
        $date = ($date ?? now())->copy()->startOfDay();
        $until = $date->copy()->setTimeFromTimeString((string) (setting('dorm.late_until') ?: '23:59'))->endOfMinute();
        $closes = $this->checkWindow($date)[1];

        return $until->lt($closes) ? $closes : $until;
    }

    public function curfewAt(?CarbonInterface $date = null): CarbonInterface
    {
        return ($date ?? now())->copy()->startOfDay()->setTimeFromTimeString($this->curfew_time);
    }
}
