<?php

namespace App\Models;

use App\Enums\DocumentationSource;
use App\Enums\SessionPhase;
use App\Services\ImageService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Throwable;

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
        static::deleted(fn (SessionDocumentation $doc) => Storage::disk('private')->delete([$doc->path, $doc->thumbnailFile()]));
    }

    /** Lokasi gambar kecil di disk private (dibuat saat pertama dibutuhkan). */
    public function thumbnailFile(): string
    {
        return dirname($this->path).'/thumbs/'.pathinfo($this->path, PATHINFO_FILENAME).'.jpg';
    }

    /**
     * Path lokal gambar kecil untuk disematkan di Excel; null bila bukan gambar atau gambar tidak bisa dibaca.
     */
    public function thumbnailPath(): ?string
    {
        if (! $this->isImage()) {
            return null;
        }

        $disk = Storage::disk('private');
        $thumb = $this->thumbnailFile();

        if (! $disk->exists($thumb)) {
            try {
                app(ImageService::class)->thumbnail($this->path, $thumb);
            } catch (Throwable) {
                return null;
            }
        }

        return $disk->path($thumb);
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
