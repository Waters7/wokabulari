<?php

namespace App\Actions\Classrooms;

use App\Models\Classroom;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Bagi rata peserta ke beberapa kelas: kelas dengan anggota paling sedikit diisi lebih dulu,
 * urutan peserta diselang-seling per jenis kelamin agar komposisi tiap kelas seimbang.
 */
class DistributeParticipants
{
    public function __construct(protected AssignParticipants $assign) {}

    /**
     * @param  Collection<int, User>  $participants  (dengan relasi profile)
     * @param  Collection<int, Classroom>  $classrooms
     * @return array<int, int> user_id => classroom_id
     */
    public function plan(Collection $participants, Collection $classrooms): array
    {
        if ($classrooms->isEmpty() || $participants->isEmpty()) {
            return [];
        }

        $movingIds = $participants->pluck('id');
        $counts = [];
        $genderCounts = [];

        // Anggota yang sudah ada (selain yang akan dibagi) ikut dihitung.
        foreach ($classrooms as $classroom) {
            $members = $classroom->participants()->whereNotIn('users.id', $movingIds)->with('profile')->get();
            $counts[$classroom->id] = $members->count();
            $genderCounts[$classroom->id] = $members->countBy(fn (User $user) => self::genderKey($user))->all();
        }

        $plan = [];

        foreach ($this->interleaveByGender($participants) as $user) {
            $gender = self::genderKey($user);
            $target = $classrooms
                ->sortBy(fn (Classroom $c) => [$counts[$c->id], $genderCounts[$c->id][$gender] ?? 0, $c->id])
                ->first();

            $plan[$user->id] = $target->id;
            $counts[$target->id]++;
            $genderCounts[$target->id][$gender] = ($genderCounts[$target->id][$gender] ?? 0) + 1;
        }

        return $plan;
    }

    /**
     * @param  array<int, int>  $plan  user_id => classroom_id
     */
    public function apply(array $plan, User $actor): int
    {
        $total = 0;

        foreach (collect($plan)->groupBy(fn ($classroomId) => $classroomId, preserveKeys: true) as $classroomId => $users) {
            $total += $this->assign->handle(Classroom::query()->findOrFail($classroomId), $users->keys(), $actor);
        }

        return $total;
    }

    /**
     * Ringkasan rencana per kelas untuk pratinjau.
     *
     * @param  array<int, int>  $plan
     * @param  Collection<int, User>  $participants
     * @return array<int, array{added: int, male: int, female: int}>
     */
    public function summary(array $plan, Collection $participants): array
    {
        $byId = $participants->keyBy('id');
        $summary = [];

        foreach ($plan as $userId => $classroomId) {
            $summary[$classroomId] ??= ['added' => 0, 'male' => 0, 'female' => 0];
            $summary[$classroomId]['added']++;
            $gender = self::genderKey($byId[$userId]);
            if (isset($summary[$classroomId][$gender])) {
                $summary[$classroomId][$gender]++;
            }
        }

        return $summary;
    }

    /** @return Collection<int, User> */
    protected function interleaveByGender(Collection $participants): Collection
    {
        $groups = $participants->sortBy('name')->groupBy(fn (User $user) => self::genderKey($user))->values();
        $result = collect();
        $max = $groups->max(fn ($group) => $group->count());

        for ($i = 0; $i < $max; $i++) {
            foreach ($groups as $group) {
                if (isset($group->values()[$i])) {
                    $result->push($group->values()[$i]);
                }
            }
        }

        return $result;
    }

    protected static function genderKey(User $user): string
    {
        return $user->profile?->gender?->value ?? 'unknown';
    }
}
