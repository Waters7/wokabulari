<?php

namespace App\Actions\Participants;

use App\Models\User;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Pulihkan peserta terhapus beserta NIS/email/HP-nya. Gagal untuk peserta yang identitasnya sudah dipakai akun lain.
 * Kamar asrama tidak dikembalikan otomatis (atur ulang lewat Atur asrama).
 */
class RestoreParticipants
{
    /**
     * @param  Collection<int, User>  $participants
     * @return array{restored: int, failed: list<string>}
     */
    public function handle(Collection $participants, User $actor): array
    {
        $restored = collect();
        $failed = [];

        foreach ($participants->filter(fn (User $user) => $user->trashed() && $actor->can('restore', $user)) as $user) {
            try {
                $user->restore();
                $restored->push($user);
            } catch (InvalidArgumentException $e) {
                $failed[] = $e->getMessage();
            }
        }

        if ($restored->isNotEmpty()) {
            activity('peserta')->causedBy($actor)
                ->withProperties(['jumlah' => $restored->count(), 'peserta' => $restored->map(fn (User $u) => $u->name.' ('.$u->username.')')->all()])
                ->log('Memulihkan peserta');
        }

        return ['restored' => $restored->count(), 'failed' => $failed];
    }
}
