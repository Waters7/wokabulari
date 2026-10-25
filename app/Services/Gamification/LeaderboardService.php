<?php

namespace App\Services\Gamification;

use App\Enums\Role;
use App\Models\Batch;
use App\Models\Classroom;
use App\Models\PointTransaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Leaderboard mingguan per kelas & antarkelas, serta peserta terbaik minggu ini.
 */
class LeaderboardService
{
    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    public static function week(?CarbonInterface $date = null): array
    {
        $start = CarbonImmutable::parse($date ?? now())->startOfWeek();

        return [$start, $start->endOfWeek()];
    }

    /**
     * Peringkat peserta berdasarkan poin pada rentang (null = semua waktu).
     *
     * @param  Collection<int, User>  $users
     * @return Collection<int, array{rank: int, user: User, points: int}>
     */
    public function rank(Collection $users, ?CarbonInterface $from = null, ?CarbonInterface $to = null): Collection
    {
        $points = PointTransaction::query()->whereIn('user_id', $users->pluck('id'))
            ->when($from && $to, fn ($q) => $q->whereBetween('created_at', [$from, $to]))
            ->selectRaw('user_id, sum(points) as total')->groupBy('user_id')->pluck('total', 'user_id');

        $rank = 0;
        $previous = null;

        return $users->map(fn (User $u) => ['user' => $u, 'points' => (int) ($points[$u->id] ?? 0)])
            ->sortBy([['points', 'desc'], [fn ($row) => $row['user']->name, 'asc']])
            ->values()
            ->map(function (array $row, int $i) use (&$rank, &$previous) {
                // Poin sama = peringkat sama.
                if ($row['points'] !== $previous) {
                    $rank = $i + 1;
                    $previous = $row['points'];
                }

                return ['rank' => $rank] + $row;
            });
    }

    public function classroom(Classroom $classroom, ?CarbonInterface $from = null, ?CarbonInterface $to = null): Collection
    {
        return $this->rank($classroom->participants()->active()->get(), $from, $to);
    }

    /**
     * Antarkelas: rata-rata poin per peserta agar adil untuk kelas dengan jumlah berbeda.
     *
     * @return Collection<int, array{rank: int, classroom: Classroom, average: float, total: int, members: int}>
     */
    public function classes(?Batch $batch, ?CarbonInterface $from = null, ?CarbonInterface $to = null): Collection
    {
        if (! $batch) {
            return collect();
        }

        return Classroom::query()->where('batch_id', $batch->id)->with(['participants' => fn ($q) => $q->where('is_active', true), 'language'])->get()
            ->map(function (Classroom $classroom) use ($from, $to) {
                $rows = $this->rank($classroom->participants, $from, $to);
                $total = (int) $rows->sum('points');

                return ['classroom' => $classroom, 'total' => $total, 'members' => $rows->count(), 'average' => $rows->count() ? round($total / $rows->count(), 1) : 0.0];
            })
            ->sortByDesc('average')->values()
            ->map(fn ($row, $i) => ['rank' => $i + 1] + $row);
    }

    /** Peserta terbaik angkatan aktif pada minggu tertentu (null bila belum ada poin). */
    public function bestOfWeek(?CarbonInterface $date = null, ?Classroom $classroom = null): ?array
    {
        [$from, $to] = self::week($date);
        $users = $classroom
            ? $classroom->participants()->active()->get()
            : $this->batchParticipants(Batch::current());

        $top = $this->rank($users, $from, $to)->first();

        return $top && $top['points'] > 0 ? $top + ['from' => $from, 'to' => $to] : null;
    }

    /** @return Collection<int, User> */
    public function batchParticipants(?Batch $batch): Collection
    {
        return User::query()->role(Role::Participant->value)->active()
            ->when($batch, fn ($q) => $q->whereHas('classrooms', fn ($c) => $c->where('batch_id', $batch->id)))
            ->get();
    }
}
