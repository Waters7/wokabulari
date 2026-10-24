<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Classroom extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = ['batch_id', 'language_id', 'name', 'level', 'teacher_id', 'location_id'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges()->useLogName('kelas');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('joined_at')->withTimestamps();
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereHas('batch', fn (Builder $query) => $query->where('is_active', true));
    }

    /** Kelas yang boleh dikelola pengguna: semua untuk admin, kelas sendiri untuk pengajar. */
    public function scopeManagedBy(Builder $query, User $user): void
    {
        if ($user->can('classroom.manage') || $user->can('session.view-all')) {
            return;
        }

        $query->where('teacher_id', $user->id);
    }

    public function label(): string
    {
        return trim($this->name.($this->level ? " · {$this->level}" : ''));
    }
}
