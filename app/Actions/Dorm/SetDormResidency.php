<?php

namespace App\Actions\Dorm;

use App\Models\DormRoomAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Tandai peserta tinggal / tidak tinggal di asrama. Peserta tidak asrama dikeluarkan dari kamarnya.
 */
class SetDormResidency
{
    /**
     * @param  iterable<int>  $userIds
     * @return int jumlah peserta yang diubah
     */
    public function handle(iterable $userIds, bool $resident, User $actor): int
    {
        $participants = User::query()->participants()->with('profile')->whereIn('id', collect($userIds)->all())->get();

        DB::transaction(function () use ($participants, $resident) {
            foreach ($participants as $participant) {
                $participant->profile()->firstOrCreate([])->update(['lives_in_dorm' => $resident]);
            }

            if (! $resident) {
                DormRoomAssignment::query()->whereIn('user_id', $participants->pluck('id'))->whereNull('check_out_date')->update(['check_out_date' => today()]);
            }
        });

        activity('asrama')->causedBy($actor)
            ->withProperties(['jumlah' => $participants->count(), 'tinggal_di_asrama' => $resident])
            ->log($resident ? 'Menandai peserta tinggal di asrama' : 'Menandai peserta tidak tinggal di asrama');

        return $participants->count();
    }
}
