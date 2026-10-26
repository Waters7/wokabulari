<?php

namespace App\Actions\Dorm;

use App\Enums\DormRequestCategory;
use App\Enums\DormRequestStatus;
use App\Enums\Priority;
use App\Models\DormRequest;
use App\Models\User;
use App\Services\ImageService;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

/**
 * Keluhan & permintaan asrama (SPEC 3.17): peserta melapor dengan foto, tim kerja memproses hingga selesai.
 */
class ManageDormRequests
{
    public function __construct(protected ImageService $images) {}

    /**
     * @param  array{category: string, description: string, priority?: ?string}  $data
     */
    public function submit(User $participant, array $data, ?UploadedFile $photo = null): DormRequest
    {
        if (! $participant->isParticipant()) {
            throw new InvalidArgumentException('Keluhan asrama diajukan oleh peserta.');
        }

        if (! $participant->livesInDorm()) {
            throw new InvalidArgumentException('Keluhan asrama hanya untuk peserta yang tinggal di asrama.');
        }

        return DormRequest::query()->create([
            'user_id' => $participant->id,
            'dorm_room_id' => $participant->currentRoomAssignment?->dorm_room_id,
            'category' => DormRequestCategory::from($data['category']),
            'description' => trim($data['description']),
            'photo_path' => $photo ? $this->images->storeUpload($photo, 'dorm-requests') : null,
            'priority' => Priority::tryFrom((string) ($data['priority'] ?? '')) ?? Priority::Normal,
            'status' => DormRequestStatus::New,
        ]);
    }

    public function update(DormRequest $request, User $officer, DormRequestStatus $status, ?string $note = null, ?Priority $priority = null): DormRequest
    {
        if ($status === DormRequestStatus::Resolved && blank($note)) {
            throw new InvalidArgumentException('Catatan penyelesaian wajib diisi.');
        }

        $request->update([
            'status' => $status,
            'priority' => $priority ?? $request->priority,
            'handled_by' => $officer->id,
            'resolution_note' => filled($note) ? trim($note) : $request->resolution_note,
            'resolved_at' => $status === DormRequestStatus::Resolved ? now() : null,
        ]);

        return $request;
    }
}
