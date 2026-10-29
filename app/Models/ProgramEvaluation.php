<?php

namespace App\Models;

use App\Enums\EvaluationCategory;
use App\Enums\PublishStatus;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Survei/evaluasi (mis. sarana & prasarana) untuk peserta dan/atau pengajar angkatan terpilih.
 */
class ProgramEvaluation extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    public const ROLE_PARTICIPANT = 'participant';

    public const ROLE_TEACHER = 'teacher';

    protected $fillable = [
        'title', 'description', 'category', 'target_participants', 'target_teachers', 'is_anonymous',
        'opens_at', 'closes_at', 'status', 'published_at', 'notified_at', 'reminded_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'category' => EvaluationCategory::class,
            'status' => PublishStatus::class,
            'target_participants' => 'boolean',
            'target_teachers' => 'boolean',
            'is_anonymous' => 'boolean',
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'published_at' => 'datetime',
            'notified_at' => 'datetime',
            'reminded_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['title', 'category', 'status', 'opens_at', 'closes_at', 'is_anonymous'])->logOnlyDirty()->dontLogEmptyChanges()->useLogName('survei');
    }

    public function batches(): BelongsToMany
    {
        return $this->belongsToMany(Batch::class, 'batch_program_evaluation');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(EvaluationQuestion::class)->orderBy('sort_order')->orderBy('id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(EvaluationResponse::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('status', PublishStatus::Published->value);
    }

    /** Sedang bisa diisi. */
    public function scopeOpen(Builder $query): void
    {
        $query->published()
            ->where(fn (Builder $q) => $q->whereNull('opens_at')->orWhere('opens_at', '<=', now()))
            ->where('closes_at', '>', now());
    }

    /** Survei terbit yang ditujukan ke pengguna (sebagai peserta atau pengajar angkatan terpilih). */
    public function scopeFor(Builder $query, User $user): void
    {
        $query->published()->where(function (Builder $q) use ($user) {
            $q->whereRaw('1 = 0');

            if ($user->isParticipant()) {
                $batchIds = $user->classrooms()->pluck('batch_id');
                $q->orWhere(fn (Builder $p) => $p->where('target_participants', true)
                    ->whereHas('batches', fn (Builder $b) => $b->whereIn('batches.id', $batchIds)));
            }

            if ($user->isTeacher()) {
                $batchIds = Classroom::query()->taughtBy($user)->pluck('batch_id');
                $q->orWhere(fn (Builder $t) => $t->where('target_teachers', true)
                    ->whereHas('batches', fn (Builder $b) => $b->whereIn('batches.id', $batchIds)));
            }
        });
    }

    public function isPublished(): bool
    {
        return $this->status === PublishStatus::Published;
    }

    public function hasStarted(): bool
    {
        return $this->opens_at === null || $this->opens_at->lte(now());
    }

    public function isClosed(): bool
    {
        return $this->closes_at->lte(now());
    }

    public function isOpen(): bool
    {
        return $this->isPublished() && $this->hasStarted() && ! $this->isClosed();
    }

    /** @return array{0: string, 1: string} label & warna status */
    public function state(): array
    {
        return match (true) {
            ! $this->isPublished() => ['Draf', 'zinc'],
            $this->isClosed() => ['Selesai', 'zinc'],
            ! $this->hasStarted() => ['Terjadwal', 'amber'],
            default => ['Berjalan', 'green'],
        };
    }

    /** Peran pengguna sebagai responden survei ini, atau null bila tidak ditujukan kepadanya. */
    public function roleFor(User $user): ?string
    {
        $batchIds = $this->batches()->pluck('batches.id');

        if ($this->target_participants && $user->isParticipant() && $user->classrooms()->whereIn('batch_id', $batchIds)->exists()) {
            return self::ROLE_PARTICIPANT;
        }

        if ($this->target_teachers && $user->isTeacher() && Classroom::query()->whereIn('batch_id', $batchIds)->taughtBy($user)->exists()) {
            return self::ROLE_TEACHER;
        }

        return null;
    }

    /** HMAC responden: mencegah pengisian ganda tanpa menyimpan identitas pada survei anonim. */
    public function respondentHash(User $user): string
    {
        return hash_hmac('sha256', $user->id.'|'.$this->id, (string) config('app.key'));
    }

    public function hasResponded(User $user): bool
    {
        return $this->responses()->where('respondent_hash', $this->respondentHash($user))->exists();
    }

    /** @return Collection<int, EvaluationQuestion> */
    public function questionsFor(string $role): Collection
    {
        return $this->questions->filter(fn (EvaluationQuestion $q) => $q->audience->includes($role))->values();
    }

    /**
     * Semua responden yang dituju (aktif).
     *
     * @return Collection<int, User>
     */
    public function respondents(): Collection
    {
        return collect([self::ROLE_PARTICIPANT, self::ROLE_TEACHER])
            ->map(fn (string $role) => $this->respondentsQuery($role)?->get() ?? collect())
            ->flatten(1)
            ->unique('id')
            ->values();
    }

    /** Responden per peran, atau null bila peran itu tidak disasar. */
    public function respondentsQuery(string $role): ?Builder
    {
        $batchIds = $this->batches()->pluck('batches.id');

        return match ($role) {
            self::ROLE_PARTICIPANT => $this->target_participants
                ? User::query()->participants()->active()->whereHas('classrooms', fn (Builder $c) => $c->whereIn('batch_id', $batchIds))
                : null,
            self::ROLE_TEACHER => $this->target_teachers
                ? User::query()->role(Role::Teacher->value)->active()->whereHas('teachingClassrooms', fn (Builder $c) => $c->whereIn('batch_id', $batchIds))
                : null,
            default => null,
        };
    }
}
