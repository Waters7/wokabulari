<?php

namespace App\Actions\Materials;

use App\Enums\MaterialType;
use App\Enums\MaterialVisibility;
use App\Models\LearningSession;
use App\Models\SessionMaterial;
use App\Models\User;
use App\Notifications\MaterialPublished;
use App\Services\ImageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Simpan materi (berkas atau tautan) ke sesi atau bank materi, lalu kabari peserta bila langsung tampil.
 */
class StoreMaterial
{
    public function __construct(protected ImageService $images) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $teacher, array $data, ?LearningSession $session = null, ?UploadedFile $file = null): SessionMaterial
    {
        $attributes = [
            'learning_session_id' => $session?->id,
            'teacher_id' => $teacher->id,
            'language_id' => $session?->classroom?->language_id ?? ($data['language_id'] ?? null),
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'level' => $data['level'] ?? $session?->classroom?->level,
            'topic' => $data['topic'] ?? $session?->topic,
            'visibility' => $data['visibility'] ?? MaterialVisibility::Immediate->value,
            'visible_from' => ($data['visibility'] ?? null) === MaterialVisibility::Scheduled->value ? $data['visible_from'] : null,
            'is_downloadable' => (bool) ($data['is_downloadable'] ?? true),
            'sort_order' => $session ? $session->materials()->count() + 1 : 0,
        ];

        if ($file) {
            $mime = $file->getMimeType();
            $directory = 'materials/'.($session?->id ?? 'bank');

            // Gambar dikompres otomatis; berkas lain disimpan apa adanya dengan nama UUID.
            $path = str_starts_with((string) $mime, 'image/')
                ? $this->images->storeUpload($file, $directory)
                : $file->storeAs($directory, Str::uuid().'.'.$file->extension(), 'private');

            $attributes += [
                'type' => MaterialType::fromMime($mime),
                'file_path' => $path,
                'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
                'mime' => str_starts_with((string) $mime, 'image/') ? 'image/jpeg' : $mime,
                'size' => Storage::disk('private')->size($path),
            ];
        } else {
            $attributes += ['type' => MaterialType::Link, 'url' => $data['url']];
        }

        $material = SessionMaterial::query()->create($attributes);

        $this->notifyIfVisible($material);

        return $material;
    }

    /** Salin materi (dari bank atau sesi lain) ke sesi tujuan. */
    public function copy(SessionMaterial $source, LearningSession $target, User $teacher): SessionMaterial
    {
        $copy = $source->replicate(['learning_session_id', 'teacher_id', 'sort_order']);
        $copy->learning_session_id = $target->id;
        $copy->teacher_id = $teacher->id;
        $copy->language_id = $target->classroom->language_id;
        $copy->sort_order = $target->materials()->count() + 1;

        if ($source->file_path && Storage::disk('private')->exists($source->file_path)) {
            $newPath = 'materials/'.$target->id.'/'.Str::uuid().'.'.pathinfo($source->file_path, PATHINFO_EXTENSION);
            Storage::disk('private')->copy($source->file_path, $newPath);
            $copy->file_path = $newPath;
        }

        $copy->save();
        $this->notifyIfVisible($copy);

        return $copy;
    }

    protected function notifyIfVisible(SessionMaterial $material): void
    {
        if (! $material->session || $material->visibility !== MaterialVisibility::Immediate) {
            return;
        }

        Notification::send($material->session->classroom->participants()->active()->get(), new MaterialPublished($material));
    }
}
