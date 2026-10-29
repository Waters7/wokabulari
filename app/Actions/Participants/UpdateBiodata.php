<?php

namespace App\Actions\Participants;

use App\Models\ParticipantProfile;
use App\Models\User;
use App\Support\Phone;
use InvalidArgumentException;

/**
 * Peserta melengkapi biodatanya sendiri. Nama, kelas, dan status asrama tetap dikelola admin;
 * jenis kelamin hanya bisa diisi sekali (sesudahnya lewat admin).
 */
class UpdateBiodata
{
    /**
     * @param  array<string, mixed>  $data  sudah divalidasi BiodataRequest
     */
    public function handle(User $participant, array $data): ParticipantProfile
    {
        if (! $participant->isParticipant()) {
            throw new InvalidArgumentException('Biodata hanya untuk peserta.');
        }

        $profile = $participant->profile ?? $participant->profile()->make();
        $clean = fn (string $key) => filled($data[$key] ?? null) ? trim((string) $data[$key]) : null;

        $profile->fill([
            'birth_place' => $clean('birth_place'),
            'birth_date' => $clean('birth_date'),
            'address' => $clean('address'),
            'school_name' => $clean('school_name'),
            'school_grade' => $clean('school_grade'),
            'major' => $clean('major'),
            'blood_type' => $clean('blood_type'),
            'allergies' => $clean('allergies'),
            'medical_history' => $clean('medical_history'),
            'emergency_contact_name' => $clean('emergency_contact_name'),
            'emergency_contact_phone' => Phone::normalize($clean('emergency_contact_phone')),
            'shirt_size' => $clean('shirt_size'),
            'target_exam' => $clean('target_exam'),
            'target_score' => $clean('target_score'),
            'biodata_updated_at' => now(),
        ]);

        if (blank($profile->gender) && filled($data['gender'] ?? null)) {
            $profile->gender = $data['gender'];
        }

        $profile->save();

        return $profile;
    }
}
