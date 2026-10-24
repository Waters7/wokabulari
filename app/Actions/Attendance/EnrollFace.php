<?php

namespace App\Actions\Attendance;

use App\Models\User;
use App\Services\FaceMatcher;
use App\Services\ImageService;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Daftarkan wajah referensi peserta dari minimal 3 sampel descriptor.
 */
class EnrollFace
{
    public function __construct(
        protected FaceMatcher $matcher,
        protected ImageService $images,
    ) {}

    /**
     * @param  list<list<float>>  $descriptors
     */
    public function handle(User $user, array $descriptors, ?string $selfieDataUri): void
    {
        if (! $user->isParticipant()) {
            throw new InvalidArgumentException('Hanya peserta yang mendaftarkan wajah.');
        }

        $average = $this->matcher->average(array_values($descriptors));

        $profile = $user->profile()->firstOrCreate([]);
        $oldPath = $profile->face_reference_path;

        $path = $selfieDataUri ? $this->images->storeDataUri($selfieDataUri, "faces/{$user->id}", 480, 80) : null;

        $profile->update([
            'face_descriptor' => $average,
            'face_reference_path' => $path,
            'face_enrolled_at' => now(),
        ]);

        if ($oldPath && $oldPath !== $path) {
            Storage::disk('private')->delete($oldPath);
        }

        activity('peserta')->causedBy($user)->performedOn($user)->log('Mendaftarkan wajah referensi');
    }
}
