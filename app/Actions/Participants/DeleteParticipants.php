<?php

namespace App\Actions\Participants;

use App\Models\DormRoomAssignment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Hapus peserta (soft delete): akun tidak bisa login dan hilang dari daftar, laporan, absen, dan nilai,
 * tetapi riwayatnya tetap tersimpan sehingga bisa dipulihkan. NIS/email/HP dilepas (lihat User::booted),
 * dan peserta keluar dari kamar asrama agar tempat tidurnya kosong kembali.
 */
class DeleteParticipants
{
    /**
     * @param  Collection<int, User>  $participants
     * @return int jumlah peserta yang dihapus
     */
    public function handle(Collection $participants, User $actor): int
    {
        $participants = $participants->filter(fn (User $user) => ! $user->trashed() && $actor->can('delete', $user))->values();

        if ($participants->isEmpty()) {
            return 0;
        }

        DB::transaction(function () use ($participants) {
            DormRoomAssignment::query()->whereIn('user_id', $participants->pluck('id'))->whereNull('check_out_date')
                ->update(['check_out_date' => today()->toDateString()]);

            $participants->each->delete();
        });

        activity('peserta')->causedBy($actor)
            ->withProperties(['jumlah' => $participants->count(), 'peserta' => $participants->map(fn (User $u) => $u->name.' ('.$u->identity('username').')')->all()])
            ->log('Menghapus peserta');

        return $participants->count();
    }
}
