<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Classroom extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = ['batch_id', 'language_id', 'name', 'level', 'teacher_id', 'location_id'];

    protected static function booted(): void
    {
        // Pengajar utama selalu termasuk tim pengajar kelas.
        static::saved(function (Classroom $classroom) {
            if ($classroom->teacher_id && ($classroom->wasRecentlyCreated || $classroom->wasChanged('teacher_id'))) {
                $classroom->teachers()->syncWithoutDetaching([$classroom->teacher_id]);
            }
        });
    }

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

    /** Pengajar utama (koordinator kelas). */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /** Semua pengajar kelas, termasuk pengajar utama. */
    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'classroom_teacher')->withTimestamps();
    }

    /** Aspek penilaian periodik yang dipilih pengajar (kosong = aspek bawaan). */
    public function assessmentAspects(): BelongsToMany
    {
        return $this->belongsToMany(AssessmentAspect::class, 'classroom_assessment_aspect');
    }

    public function hasTeacher(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->teacher_id === $user->id) {
            return true;
        }

        return $this->relationLoaded('teachers')
            ? $this->teachers->contains('id', $user->id)
            : $this->teachers()->whereKey($user->id)->exists();
    }

    /**
     * Atur tim pengajar: pengajar utama + pengajar lain.
     *
     * @param  iterable<int>  $otherIds
     */
    public function syncTeachers(?int $leadId, iterable $otherIds = []): void
    {
        $this->forceFill(['teacher_id' => $leadId])->save();
        $this->teachers()->sync(collect($otherIds)->push($leadId)->filter()->map(fn ($id) => (int) $id)->unique()->values()->all());
        $this->unsetRelation('teachers');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('joined_at')->withTimestamps();
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(LearningSession::class)->orderBy('date')->orderBy('start_time');
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

        $query->taughtBy($user);
    }

    /** Kelas yang diajar pengguna (sebagai pengajar utama atau anggota tim pengajar). */
    public function scopeTaughtBy(Builder $query, User|int $user): void
    {
        $id = $user instanceof User ? $user->id : $user;

        $query->where(fn (Builder $q) => $q->where('classrooms.teacher_id', $id)
            ->orWhereHas('teachers', fn (Builder $t) => $t->whereKey($id)));
    }

    public function label(): string
    {
        return trim($this->name.($this->level ? " · {$this->level}" : ''));
    }
}
