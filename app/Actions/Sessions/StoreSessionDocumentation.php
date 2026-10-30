<?php

namespace App\Actions\Sessions;

use App\Enums\DocumentationSource;
use App\Enums\SessionPhase;
use App\Models\LearningSession;
use App\Models\SessionDocumentation;
use App\Models\User;
use App\Services\ImageService;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use Throwable;

/**
 * Simpan foto/file dokumentasi kelas ke disk private. Foto dikompres; file lain (PDF, HEIC) disimpan apa adanya.
 */
class StoreSessionDocumentation
{
    public const MAX_PER_PHASE = 5;

    public function __construct(protected ImageService $images) {}

    public function handle(LearningSession $session, User $by, SessionPhase $phase, UploadedFile $file, DocumentationSource $source): SessionDocumentation
    {
        if ($session->documentations()->where('phase', $phase)->count() >= self::MAX_PER_PHASE) {
            throw new InvalidArgumentException('Maksimal '.self::MAX_PER_PHASE.' dokumentasi untuk '.strtolower($phase->label()).'.');
        }

        $folder = "sesi/{$session->id}/dokumentasi";
        $mime = (string) $file->getMimeType();
        $path = null;

        if (in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            try {
                $path = $this->images->storeUpload($file, $folder, 1600, 75);
                $mime = 'image/jpeg';
            } catch (Throwable) {
                $path = null;
            }
        }

        $path ??= $file->store($folder, 'private');

        return $session->documentations()->create([
            'phase' => $phase,
            'source' => $source,
            'path' => $path,
            'original_name' => $source === DocumentationSource::Upload ? mb_substr($file->getClientOriginalName(), 0, 255) : null,
            'mime_type' => $mime,
            'size' => (int) ($file->getSize() ?: 0),
            'uploaded_by' => $by->id,
        ]);
    }
}
