<?php

namespace App\Actions\Participants;

use App\Enums\Gender;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Isi jenis kelamin banyak peserta sekaligus (dibutuhkan untuk penempatan asrama putra/putri).
 */
class SetParticipantGender
{
    /**
     * @param  iterable<int>  $userIds
     * @return int jumlah peserta yang diubah
     */
    public function handle(iterable $userIds, Gender $gender, User $actor): int
    {
        $participants = User::query()->participants()->whereIn('id', collect($userIds)->all())->get();

        DB::transaction(function () use ($participants, $gender) {
            foreach ($participants as $participant) {
                $participant->profile()->firstOrCreate([])->update(['gender' => $gender]);
            }
        });

        activity('peserta')->causedBy($actor)
            ->withProperties(['jumlah' => $participants->count(), 'jenis_kelamin' => $gender->value])
            ->log('Mengisi jenis kelamin peserta secara massal');

        return $participants->count();
    }
}
