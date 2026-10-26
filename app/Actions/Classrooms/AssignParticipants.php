<?php

namespace App\Actions\Classrooms;

use App\Models\Classroom;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Tempatkan banyak peserta ke satu kelas sekaligus.
 * Satu peserta hanya di satu kelas per angkatan, jadi kelas lain di angkatan yang sama dilepas.
 */
class AssignParticipants
{
    /**
     * @param  iterable<int>  $userIds
     * @return int jumlah peserta yang ditempatkan
     */
    public function handle(Classroom $classroom, iterable $userIds, User $actor): int
    {
        $ids = User::query()->participants()->whereIn('id', collect($userIds)->all())->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        DB::transaction(function () use ($classroom, $ids) {
            $otherClasses = Classroom::query()->where('batch_id', $classroom->batch_id)->whereKeyNot($classroom->id)->pluck('id');
            DB::table('classroom_user')->whereIn('user_id', $ids)->whereIn('classroom_id', $otherClasses)->delete();

            // Yang sudah di kelas ini tidak diubah tanggal bergabungnya.
            $existing = $classroom->participants()->whereIn('users.id', $ids)->pluck('users.id');
            $classroom->participants()->attach($ids->diff($existing)->mapWithKeys(fn ($id) => [$id => ['joined_at' => today()]])->all());
        });

        activity('kelas')->causedBy($actor)->performedOn($classroom)
            ->withProperties(['jumlah' => $ids->count()])
            ->log('Menempatkan peserta ke kelas secara massal');

        return $ids->count();
    }
}
