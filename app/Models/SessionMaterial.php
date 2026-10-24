<?php

namespace App\Models;

use App\Enums\MaterialType;
use App\Enums\MaterialVisibility;
use App\Enums\SessionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\URL;

class SessionMaterial extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'learning_session_id', 'teacher_id', 'language_id', 'title', 'description', 'level', 'topic', 'type',
        'file_path', 'url', 'original_name', 'mime', 'size', 'visibility', 'visible_from', 'is_downloadable', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => MaterialType::class,
            'visibility' => MaterialVisibility::class,
            'visible_from' => 'datetime',
            'is_downloadable' => 'boolean',
            'size' => 'integer',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(LearningSession::class, 'learning_session_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(MaterialAccessLog::class);
    }

    /** Materi yang sudah boleh dilihat peserta. */
    public function scopeVisibleToParticipants(Builder $query): void
    {
        $query->where(function (Builder $q) {
            $q->where('visibility', MaterialVisibility::Immediate)
                ->orWhere(fn (Builder $q) => $q->where('visibility', MaterialVisibility::Scheduled)->where('visible_from', '<=', now()))
                ->orWhere(fn (Builder $q) => $q->where('visibility', MaterialVisibility::AfterSession)
                    ->whereHas('session', fn (Builder $s) => $s->where('status', SessionStatus::Completed)));
        });
    }

    public function isVisibleNow(): bool
    {
        return match ($this->visibility) {
            MaterialVisibility::Immediate => true,
            MaterialVisibility::Scheduled => $this->visible_from?->isPast() ?? false,
            MaterialVisibility::AfterSession => $this->session?->isCompleted() ?? false,
        };
    }

    public function isFile(): bool
    {
        return $this->type !== MaterialType::Link && filled($this->file_path);
    }

    public function isPdf(): bool
    {
        return $this->mime === 'application/pdf';
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }

    /** Signed URL untuk pratinjau/unduh (berlaku 30 menit). */
    public function fileUrl(bool $download = false): ?string
    {
        if (! $this->isFile()) {
            return null;
        }

        return URL::temporarySignedRoute('materials.file', now()->addMinutes(30), ['material' => $this->id, 'unduh' => $download ? 1 : 0]);
    }

    /** URL embed YouTube bila tautan berasal dari YouTube. */
    public function youtubeEmbedUrl(): ?string
    {
        if ($this->type !== MaterialType::Link || ! $this->url) {
            return null;
        }

        if (preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|shorts/))([\w-]{11})~', $this->url, $m)) {
            return 'https://www.youtube-nocookie.com/embed/'.$m[1];
        }

        return null;
    }

    public function sizeLabel(): string
    {
        if (! $this->size) {
            return '';
        }

        return $this->size >= 1048576 ? angka($this->size / 1048576, 1).' MB' : angka($this->size / 1024).' KB';
    }
}
