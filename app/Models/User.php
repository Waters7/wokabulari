<?php

namespace App\Models;

use App\Enums\Role;
use App\Enums\StaffUnit;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, LogsActivity, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'username', 'phone', 'password', 'photo_path',
        'is_active', 'must_change_password', 'leaderboard_anonymous', 'consented_at', 'consent_version', 'last_login_at',
    ];

    protected $attributes = [
        'is_active' => true,
        'must_change_password' => false,
        'leaderboard_anonymous' => false,
    ];

    /** Urutan prioritas untuk menentukan role utama pengguna. */
    protected const ROLE_PRIORITY = [
        Role::SuperAdmin, Role::Leader, Role::ProgramAdmin, Role::Teacher,
        Role::Staff, Role::Guardian, Role::Participant,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'leaderboard_anonymous' => 'boolean',
            'consented_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'username', 'phone', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('pengguna');
    }

    // ---- Relasi

    public function profile(): HasOne
    {
        return $this->hasOne(ParticipantProfile::class);
    }

    public function staffUnits(): HasMany
    {
        return $this->hasMany(StaffUnitAssignment::class);
    }

    /** Kelas yang diikuti peserta. */
    public function classrooms(): BelongsToMany
    {
        return $this->belongsToMany(Classroom::class)->withPivot('joined_at')->withTimestamps();
    }

    /** Kelas yang diajar (pengajar utama). */
    public function teachingClassrooms(): HasMany
    {
        return $this->hasMany(Classroom::class, 'teacher_id');
    }

    public function roomAssignments(): HasMany
    {
        return $this->hasMany(DormRoomAssignment::class);
    }

    public function currentRoomAssignment(): HasOne
    {
        return $this->hasOne(DormRoomAssignment::class)->whereNull('check_out_date')->latestOfMany('check_in_date');
    }

    // ---- Role & unit

    public function primaryRole(): ?Role
    {
        $names = $this->getRoleNames();

        foreach (self::ROLE_PRIORITY as $role) {
            if ($names->contains($role->value)) {
                return $role;
            }
        }

        return null;
    }

    public function isRole(Role $role): bool
    {
        return $this->hasRole($role->value);
    }

    public function isParticipant(): bool
    {
        return $this->hasRole(Role::Participant->value);
    }

    public function isGuardian(): bool
    {
        return $this->hasRole(Role::Guardian->value);
    }

    public function isTeacher(): bool
    {
        return $this->hasRole(Role::Teacher->value);
    }

    public function isLeader(): bool
    {
        return $this->hasRole(Role::Leader->value);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(Role::SuperAdmin->value);
    }

    public function hasUnit(StaffUnit $unit): bool
    {
        if (! $this->relationLoaded('staffUnits')) {
            $this->load('staffUnits');
        }

        return $this->staffUnits->contains(fn (StaffUnitAssignment $assignment) => $assignment->unit === $unit);
    }

    /** Kelas aktif peserta (angkatan aktif), dipakai untuk absen & sesi. */
    public function activeClassroom(): ?Classroom
    {
        return $this->classrooms()
            ->whereHas('batch', fn (Builder $query) => $query->where('is_active', true))
            ->with(['batch', 'language', 'location'])
            ->first();
    }

    /** Asrama tempat peserta tinggal saat ini. */
    public function currentDormitory(): ?Dormitory
    {
        return $this->currentRoomAssignment?->room?->dormitory;
    }

    // ---- Scope

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeParticipants(Builder $query): void
    {
        $query->role(Role::Participant->value);
    }

    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $query->where(function (Builder $query) use ($term) {
            $query->where('name', 'like', "%{$term}%")
                ->orWhere('username', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%");
        });
    }

    // ---- Accessor tampilan

    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    /** Nama untuk leaderboard: inisial bila peserta memilih menyembunyikan nama. */
    public function displayName(): string
    {
        return $this->leaderboard_anonymous ? $this->initials().'.' : $this->name;
    }

    protected function photoUrl(): Attribute
    {
        return Attribute::get(fn () => $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null);
    }

    protected function roleLabel(): Attribute
    {
        return Attribute::get(fn () => $this->primaryRole()?->label() ?? 'Pengguna');
    }

    public function needsConsent(): bool
    {
        if (! $this->hasAnyRole([Role::Participant->value, Role::Guardian->value])) {
            return false;
        }

        return $this->consented_at === null || $this->consent_version !== config('wokabulari.consent_version');
    }
}
