<?php

namespace App\Models;

use App\Enums\AnnouncementAudience;
use App\Enums\AnnouncementSource;
use App\Enums\Role;
use App\Enums\StaffUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Announcement extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = ['title', 'body', 'source', 'audience', 'target_id', 'target_role', 'is_pinned', 'notify_whatsapp', 'published_at', 'notified_at', 'created_by'];

    protected function casts(): array
    {
        return [
            'source' => AnnouncementSource::class,
            'audience' => AnnouncementAudience::class,
            'target_role' => Role::class,
            'is_pinned' => 'boolean',
            'notify_whatsapp' => 'boolean',
            'published_at' => 'datetime',
            'notified_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['title', 'source', 'audience', 'target_id', 'target_role', 'published_at'])->logOnlyDirty()->dontLogEmptyChanges()->useLogName('pengumuman');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(AnnouncementRead::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class, 'target_id');
    }

    public function dormitory(): BelongsTo
    {
        return $this->belongsTo(Dormitory::class, 'target_id');
    }

    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    /**
     * Pengumuman terbit yang ditujukan ke pengguna (semua, peran, kelas, atau asrama yang terkait dengannya).
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $roles = $user->getRoleNames()->all();
        $classrooms = self::classroomIdsFor($user);
        $dormitories = self::dormitoryIdsFor($user);

        $query->published()->where(function (Builder $q) use ($roles, $classrooms, $dormitories) {
            $q->where('audience', AnnouncementAudience::All)
                ->orWhere(fn ($r) => $r->where('audience', AnnouncementAudience::Role)->whereIn('target_role', $roles))
                ->orWhere(fn ($c) => $c->where('audience', AnnouncementAudience::Classroom)->whereIn('target_id', $classrooms))
                ->orWhere(fn ($d) => $d->where('audience', AnnouncementAudience::Dormitory)->whereIn('target_id', $dormitories));
        });
    }

    /** @return Collection<int, int> */
    public static function classroomIdsFor(User $user): Collection
    {
        $ids = collect();

        if ($user->isParticipant()) {
            $ids = $ids->merge($user->classrooms()->pluck('classrooms.id'));
        }
        if ($user->isTeacher()) {
            $ids = $ids->merge(Classroom::query()->taughtBy($user)->pluck('classrooms.id'));
        }
        if ($user->isGuardian()) {
            $ids = $ids->merge(Classroom::query()->whereHas('participants', fn ($q) => $q->whereIn('users.id', $user->wards()->pluck('users.id')))->pluck('id'));
        }

        return $ids->unique()->values();
    }

    /** @return Collection<int, int> */
    public static function dormitoryIdsFor(User $user): Collection
    {
        if ($user->hasUnit(StaffUnit::Dormitory)) {
            return Dormitory::query()->pluck('id');
        }

        $people = collect($user->isParticipant() ? [$user] : [])->merge($user->isGuardian() ? $user->wards()->get() : []);

        return $people->map(fn (User $p) => $p->currentDormitory()?->id)->filter()->unique()->values();
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->lte(now());
    }

    public function isScheduled(): bool
    {
        return $this->published_at !== null && $this->published_at->isFuture();
    }

    /** Teks sasaran untuk ditampilkan, mis. "Kelas Inggris A" atau "Wali". */
    public function audienceLabel(): string
    {
        return match ($this->audience) {
            AnnouncementAudience::All => 'Semua',
            AnnouncementAudience::Classroom => $this->classroom?->name ?? 'Kelas',
            AnnouncementAudience::Dormitory => $this->dormitory?->name ?? 'Asrama',
            AnnouncementAudience::Role => $this->target_role?->label() ?? 'Peran',
        };
    }

    /**
     * Penerima notifikasi pengumuman ini.
     *
     * @return Collection<int, User>
     */
    public function recipients(): Collection
    {
        $query = User::query()->where('is_active', true);

        $users = match ($this->audience) {
            AnnouncementAudience::All => $query->get(),
            AnnouncementAudience::Role => $query->role($this->target_role?->value ?? '')->get(),
            AnnouncementAudience::Classroom => $this->classroom
                ? $this->classroom->participants()->where('is_active', true)->get()
                    ->merge($this->classroom->participants()->get()->flatMap(fn (User $p) => $p->guardians()->get()))
                    ->merge($this->classroom->teachers()->get())->push($this->classroom->teacher)->filter()->unique('id')
                : collect(),
            AnnouncementAudience::Dormitory => $this->dormitory
                ? $this->dormitory->residents()->where('is_active', true)->get()
                    ->flatMap(fn (User $p) => collect([$p])->merge($p->guardians()->get()))
                : collect(),
        };

        return $users->unique('id')->reject(fn (User $u) => $u->id === $this->created_by)->values();
    }
}
