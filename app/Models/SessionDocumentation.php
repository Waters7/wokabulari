<?php

namespace App\Models;

use App\Enums\DocumentationSource;
use App\Enums\SessionPhase;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Foto/file dokumentasi kelas saat mulai atau akhir sesi (disk private, dibuka lewat URL bertanda tangan).
 */
class SessionDocumentation extends Model
{
    use HasFactory;

    protected $fillable = ['learning_session_id', 'phase', 'source', 'path', 'original_name', 'mime_type', 'size', 'uploaded_by'];

    protected function casts(): array
    {
        return [
            'phase' => SessionPhase::class,
            'source' => DocumentationSource::class,
            'size' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleted(fn (SessionDocumentation $doc) => Storage::disk('private')->delete($doc->path));
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(LearningSession::class, 'learning_session_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    public function url(): string
    {
        return Storage::disk('private')->temporaryUrl($this->path, now()->addMinutes(30));
    }
}
