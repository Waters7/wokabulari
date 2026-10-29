<?php

namespace App\Actions\Participants;

use App\Enums\Role;
use App\Models\Classroom;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Simpan data peserta (akun + profil + kelas) dari form admin.
 */
class SaveParticipant
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?User $user = null): User
    {
        return DB::transaction(function () use ($data, $user) {
            $isNew = $user === null;

            if ($isNew) {
                $password = config('wokabulari.participant_default_password');

                if (blank($password)) {
                    throw new InvalidArgumentException('Kata sandi awal peserta belum diatur (PARTICIPANT_DEFAULT_PASSWORD).');
                }

                $user = new User(['password' => $password, 'must_change_password' => true]);
            }

            $user->fill([
                'name' => $data['name'],
                'username' => $data['username'] ?: null,
                'email' => $data['email'] ? Str::lower($data['email']) : null,
                'phone' => Phone::normalize($data['phone'] ?? null),
                'is_active' => (bool) ($data['is_active'] ?? true),
            ])->save();

            if (! $user->hasRole(Role::Participant->value)) {
                $user->assignRole(Role::Participant->value);
            }

            $user->profile()->updateOrCreate([], [
                'school_name' => $data['school_name'] ?? null,
                'major' => $data['major'] ?? null,
                'gender' => $data['gender'] ?: null,
                'birth_date' => $data['birth_date'] ?: null,
                'allergies' => $data['allergies'] ?? null,
                'medical_history' => $data['medical_history'] ?? null,
                'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => Phone::normalize($data['emergency_contact_phone'] ?? null),
                'guardian_name' => filled($data['guardian_name'] ?? null) ? trim($data['guardian_name']) : null,
                'guardian_relationship' => ($data['guardian_relationship'] ?? null) ?: null,
                'guardian_phone' => Phone::normalize($data['guardian_phone'] ?? null),
                'lives_in_dorm' => (bool) ($data['lives_in_dorm'] ?? true),
                'target_exam' => $data['target_exam'] ?? null,
                'target_score' => $data['target_score'] ?? null,
            ]);

            // Peserta pulang-pergi tidak menempati kamar.
            if (! ($data['lives_in_dorm'] ?? true)) {
                $user->roomAssignments()->whereNull('check_out_date')->update(['check_out_date' => today()]);
            }

            if (! empty($data['classroom_id'])) {
                $classroom = Classroom::findOrFail($data['classroom_id']);
                // Satu kelas per angkatan.
                $user->classrooms()->where('batch_id', $classroom->batch_id)->where('classrooms.id', '!=', $classroom->id)->detach();
                $user->classrooms()->syncWithoutDetaching([$classroom->id => ['joined_at' => now()]]);
            }

            return $user;
        });
    }

    /** Kembalikan kata sandi ke sandi awal dan wajibkan ganti saat login berikutnya. */
    public function resetPassword(User $user): void
    {
        $password = config('wokabulari.participant_default_password');

        if (blank($password)) {
            throw new InvalidArgumentException('Kata sandi awal peserta belum diatur (PARTICIPANT_DEFAULT_PASSWORD).');
        }

        $user->forceFill(['password' => $password, 'must_change_password' => true])->save();

        activity('pengguna')->causedBy(auth()->user())->performedOn($user)->log('Mengatur ulang kata sandi peserta ke sandi awal');
    }

    /**
     * Reset massal kata sandi peserta ke sandi awal; semuanya wajib ganti saat login berikutnya.
     *
     * @param  iterable<int>  $userIds
     * @return int jumlah peserta yang direset
     */
    public function resetPasswords(iterable $userIds, User $actor): int
    {
        $password = config('wokabulari.participant_default_password');

        if (blank($password)) {
            throw new InvalidArgumentException('Kata sandi awal peserta belum diatur (PARTICIPANT_DEFAULT_PASSWORD).');
        }

        $participants = User::query()->participants()->whereIn('id', collect($userIds)->all())->get();
        // Hash sekali untuk semua peserta.
        $hash = Hash::make($password);

        DB::transaction(function () use ($participants, $hash) {
            foreach ($participants as $participant) {
                $participant->forceFill(['password' => $hash, 'must_change_password' => true])->saveQuietly();
            }
        });

        activity('pengguna')->causedBy($actor)
            ->withProperties(['jumlah' => $participants->count(), 'peserta' => $participants->pluck('name')->all()])
            ->log('Mengatur ulang kata sandi peserta secara massal');

        return $participants->count();
    }
}
