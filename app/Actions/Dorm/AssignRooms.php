<?php

namespace App\Actions\Dorm;

use App\Models\DormRoom;
use App\Models\DormRoomAssignment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Penempatan peserta di kamar asrama: satu per satu, atau isi kamar kosong otomatis
 * sesuai jenis kelamin asrama dan kapasitas kamar.
 */
class AssignRooms
{
    /** Tempatkan peserta di kamar (penempatan lama ditutup); peserta otomatis berstatus tinggal di asrama. */
    public function assign(DormRoom $room, User $participant, User $actor): DormRoomAssignment
    {
        if (! $participant->isParticipant()) {
            throw new InvalidArgumentException('Hanya peserta yang bisa ditempatkan di kamar.');
        }

        $room->loadMissing('dormitory');
        $gender = $participant->profile?->gender;

        if ($gender && $room->dormitory->gender !== $gender) {
            throw new InvalidArgumentException("{$participant->name} tidak bisa ditempatkan di asrama {$room->dormitory->gender->dormLabel()}.");
        }

        $current = $participant->currentRoomAssignment;
        if ($current?->dorm_room_id === $room->id) {
            return $current;
        }

        if ($room->activeAssignments()->count() >= $room->capacity) {
            throw new InvalidArgumentException("Kamar {$room->number} sudah penuh.");
        }

        $assignment = DB::transaction(function () use ($room, $participant) {
            DormRoomAssignment::query()->where('user_id', $participant->id)->whereNull('check_out_date')->update(['check_out_date' => today()]);
            $participant->profile()->firstOrCreate([])->update(['lives_in_dorm' => true]);

            return $room->assignments()->create(['user_id' => $participant->id, 'check_in_date' => today()]);
        });

        $participant->unsetRelation('currentRoomAssignment');

        activity('asrama')->causedBy($actor)->performedOn($participant)
            ->withProperties(['kamar' => $room->number, 'asrama' => $room->dormitory->name])
            ->log('Menempatkan peserta di kamar');

        return $assignment;
    }

    /**
     * Rencana isi kamar otomatis untuk peserta yang belum punya kamar.
     *
     * @param  Collection<int, User>  $participants  (dengan relasi profile & currentRoomAssignment)
     * @return array{placements: array<int, int>, skipped: array<int, string>} user_id => room_id / alasan
     */
    public function plan(Collection $participants): array
    {
        $rooms = DormRoom::query()->with('dormitory')->withCount('activeAssignments')->get()
            ->filter(fn (DormRoom $room) => $room->dormitory !== null)
            // Urut asrama, lantai, lalu nomor kamar secara alami (2 sebelum 10).
            ->sort(fn (DormRoom $a, DormRoom $b) => strcmp($a->dormitory->name, $b->dormitory->name)
                ?: strnatcmp((string) $a->floor, (string) $b->floor)
                ?: strnatcmp($a->number, $b->number))
            ->values();
        $free = $rooms->mapWithKeys(fn (DormRoom $room) => [$room->id => max(0, $room->capacity - $room->active_assignments_count)])->all();

        $placements = [];
        $skipped = [];

        foreach ($participants->sortBy('name') as $user) {
            $gender = $user->profile?->gender;

            if ($user->profile?->lives_in_dorm === false) {
                $skipped[$user->id] = 'Tidak tinggal di asrama';
            } elseif ($user->currentRoomAssignment) {
                $skipped[$user->id] = 'Sudah di kamar '.$user->currentRoomAssignment->room?->number;
            } elseif (! $gender) {
                $skipped[$user->id] = 'Jenis kelamin belum diisi';
            } elseif ($room = $rooms->first(fn (DormRoom $room) => $room->dormitory->gender === $gender && $free[$room->id] > 0)) {
                $placements[$user->id] = $room->id;
                $free[$room->id]--;
            } else {
                $skipped[$user->id] = 'Kamar asrama '.mb_strtolower($gender->dormLabel()).' penuh';
            }
        }

        return ['placements' => $placements, 'skipped' => $skipped];
    }

    /**
     * @param  array<int, int>  $placements  user_id => room_id
     * @return int jumlah peserta yang berhasil ditempatkan
     */
    public function apply(array $placements, User $actor): int
    {
        $rooms = DormRoom::query()->with('dormitory')->whereIn('id', array_unique($placements))->get()->keyBy('id');
        $users = User::query()->participants()->with(['profile', 'currentRoomAssignment'])->whereIn('id', array_keys($placements))->get()->keyBy('id');
        $placed = 0;

        foreach ($placements as $userId => $roomId) {
            if (! $users->has($userId) || ! $rooms->has($roomId)) {
                continue;
            }

            try {
                $this->assign($rooms[$roomId], $users[$userId], $actor);
                $placed++;
            } catch (InvalidArgumentException) {
                // Kamar terisi oleh petugas lain sejak pratinjau dibuat: lewati.
            }
        }

        return $placed;
    }
}
