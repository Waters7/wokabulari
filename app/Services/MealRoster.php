<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Batch;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Daftar peserta penerima konsumsi dan penanda penyesuaian porsi (izin, sakit, keluar asrama).
 */
class MealRoster
{
    /** Sumber penanda tambahan dari modul lain (asrama, kesehatan). */
    protected static array $flagSources = [];

    /**
     * @param  callable(CarbonInterface, Collection<int, int>): array<int, list<string>>  $source
     */
    public static function addFlagSource(callable $source): void
    {
        static::$flagSources[] = $source;
    }

    /** @return Collection<int, User> */
    public function participants(): Collection
    {
        $batch = Batch::current();

        return User::query()
            ->role(Role::Participant->value)
            ->active()
            ->when($batch, fn ($q) => $q->whereHas('classrooms', fn ($c) => $c->where('batch_id', $batch->id)))
            ->with(['profile', 'currentRoomAssignment.room.dormitory'])
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  Collection<int, int>  $userIds
     * @return array<int, list<string>> user_id => label penanda
     */
    public function flags(CarbonInterface $date, Collection $userIds): array
    {
        $flags = [];

        LeaveRequest::query()->approved()->covering($date)->whereIn('user_id', $userIds)->get()
            ->each(function (LeaveRequest $leave) use (&$flags) {
                $flags[$leave->user_id][] = $leave->type->label().' kelas';
            });

        foreach (static::$flagSources as $source) {
            foreach ($source($date, $userIds) as $userId => $labels) {
                $flags[$userId] = array_merge($flags[$userId] ?? [], $labels);
            }
        }

        return $flags;
    }
}
