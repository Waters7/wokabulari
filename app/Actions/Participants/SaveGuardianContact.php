<?php

namespace App\Actions\Participants;

use App\Enums\GuardianRelationship;
use App\Models\ParticipantProfile;
use App\Models\User;
use App\Support\Phone;
use InvalidArgumentException;

/**
 * Simpan kontak orang tua/wali peserta (wajib sebelum memakai aplikasi).
 */
class SaveGuardianContact
{
    /**
     * @param  array{guardian_name: string, guardian_relationship: string, guardian_phone: string}  $data
     */
    public function handle(User $participant, array $data): ParticipantProfile
    {
        $phone = Phone::normalize($data['guardian_phone']);

        if ($phone && $phone === $participant->phone) {
            throw new InvalidArgumentException('No. HP orang tua/wali tidak boleh sama dengan No. HP ngana sendiri.');
        }

        $profile = $participant->profile()->firstOrCreate([]);
        $profile->update([
            'guardian_name' => trim($data['guardian_name']),
            'guardian_relationship' => GuardianRelationship::from($data['guardian_relationship']),
            'guardian_phone' => $phone,
        ]);

        return $profile;
    }
}
