<?php

namespace App\Actions\Sessions;

use App\Enums\SessionStatus;
use App\Models\Classroom;
use App\Models\LearningSession;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Buat satu sesi atau jadwal berulang (mis. Senin–Jumat selama 4 minggu).
 */
class ScheduleSessions
{
    /**
     * @param  array{date: string, start_time: string, end_time: string, topic: string, objectives?: string|null, repeat?: bool, weekdays?: list<int|string>, weeks?: int}  $data
     * @return Collection<int, LearningSession>
     */
    public function handle(Classroom $classroom, array $data, ?int $teacherId = null): Collection
    {
        $start = Carbon::parse($data['date'])->startOfDay();
        $dates = collect([$start]);

        if (! empty($data['repeat'])) {
            $weekdays = collect($data['weekdays'] ?? [])->map(fn ($d) => (int) $d)->filter(fn ($d) => $d >= 1 && $d <= 7)->unique();
            $weeks = max(1, min(26, (int) ($data['weeks'] ?? 1)));

            if ($weekdays->isEmpty()) {
                throw new InvalidArgumentException('Pilih minimal satu hari untuk jadwal berulang.');
            }

            $end = $start->copy()->addWeeks($weeks)->subDay();
            $dates = collect();
            for ($day = $start->copy(); $day->lte($end); $day = $day->addDay()) {
                if ($weekdays->contains($day->dayOfWeekIso)) {
                    $dates->push($day->copy());
                }
            }
        }

        return DB::transaction(fn () => $dates->map(fn (CarbonInterface $date) => LearningSession::query()->create([
            'classroom_id' => $classroom->id,
            'teacher_id' => $teacherId ?? $classroom->teacher_id,
            'date' => $date->toDateString(),
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'topic' => $data['topic'],
            'objectives' => $data['objectives'] ?? null,
            'status' => SessionStatus::Scheduled,
        ])));
    }
}
