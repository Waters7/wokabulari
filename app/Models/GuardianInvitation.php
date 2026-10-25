<?php

namespace App\Models;

use App\Enums\GuardianRelationship;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuardianInvitation extends Model
{
    use HasFactory;

    protected $fillable = ['participant_id', 'phone', 'email', 'relationship', 'code', 'expires_at', 'used_at', 'used_by', 'created_by'];

    protected function casts(): array
    {
        return [
            'relationship' => GuardianRelationship::class,
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'participant_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function usedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by');
    }

    public function scopeUsable(Builder $query): void
    {
        $query->whereNull('used_at')->where('expires_at', '>', now());
    }

    public function isUsable(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }

    /** Dibuat oleh admin (bukan kode yang dibagikan peserta sendiri). */
    public function isFromAdmin(): bool
    {
        return $this->created_by !== null && $this->created_by !== $this->participant_id;
    }

    public function url(): string
    {
        return route('guardian.register', $this->code);
    }
}
