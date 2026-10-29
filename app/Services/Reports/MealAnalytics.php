<?php

namespace App\Services\Reports;

use App\Enums\DeliveryUnit;
use App\Enums\MealType;
use App\Models\MealSchedule;
use App\Models\User;
use App\Services\MealRoster;
use Illuminate\Support\Collection;

/**
 * Konsumsi: porsi dipesan, jumlah diterima dari vendor (input tim konsumsi), dan tanda terima peserta.
 * Tanda terima & peserta berhak mengikuti cakupan filter (angkatan/kelas) dan aturan peserta tidak asrama.
 */
class MealAnalytics
{
    public function __construct(protected MealRoster $roster) {}

    /**
     * @return array{
     *     schedules: Collection<int, array{schedule: MealSchedule, eligible: int, received: int, proxied: int, delivered: ?int, rate: ?float}>,
     *     days: Collection<string, array{ordered: int, delivered: int, received: int, eligible: int}>,
     *     types: Collection<string, array{type: MealType, schedules: int, eligible: int, received: int, rate: ?float}>,
     *     vendors: Collection<int, array{vendor: mixed, schedules: int, ordered: int, delivered: array<string, int>, received: int}>,
     *     participants: Collection<int, array<string, int>>,
     *     totals: array{ordered: int, delivered: int, received: int, eligible: int, proxied: int, rate: ?float}
     * }
     */
    public function compute(ReportFilter $filter): array
    {
        $participants = $filter->participants();
        // Tim pengajar/tim kerja penerima konsumsi ikut dihitung bila laporan tidak dibatasi satu kelas.
        $staff = $filter->classroomId ? collect() : $this->roster->staff();
        $ids = [...$filter->participantIds(), ...$staff->pluck('id')->map(fn ($id) => (int) $id)->all()];
        $residentCount = $participants->filter(fn (User $user) => $user->profile?->lives_in_dorm !== false)->count();

        $schedules = MealSchedule::query()->with(['vendor', 'delivery.reporter', 'receipts' => fn ($q) => $q->whereIn('user_id', $ids)->with('proxy:id,name')->orderBy('received_at')])
            ->withCount('receipts as receipts_total')
            ->whereDateBetween('date', $filter->from->toDateString(), $filter->to->toDateString())
            ->orderBy('date')->orderBy('starts_at')->get()
            ->map(function (MealSchedule $schedule) use ($participants, $residentCount, $staff) {
                $eligible = ($this->roster->servesNonResidents($schedule->meal_type) ? $participants->count() : $residentCount)
                    + ($this->roster->servesStaff($schedule->meal_type) ? $staff->count() : 0);
                $received = $schedule->receipts->count();

                return [
                    'schedule' => $schedule,
                    'eligible' => $eligible,
                    'received' => $received,
                    'proxied' => $schedule->receipts->whereNotNull('proxy_by')->count(),
                    'delivered' => $schedule->delivery?->quantity,
                    'rate' => $eligible > 0 ? round($received / $eligible * 100, 1) : null,
                ];
            });

        $days = $schedules->groupBy(fn ($row) => $row['schedule']->date->toDateString())->map(fn (Collection $rows) => [
            'ordered' => (int) $rows->sum(fn ($r) => $r['schedule']->portions_ordered),
            'delivered' => (int) $rows->sum(fn ($r) => $r['delivered'] ?? 0),
            'received' => (int) $rows->sum('received'),
            'eligible' => (int) $rows->sum('eligible'),
        ]);

        $types = collect(MealType::cases())->mapWithKeys(function (MealType $type) use ($schedules) {
            $rows = $schedules->filter(fn ($r) => $r['schedule']->meal_type === $type);
            $eligible = (int) $rows->sum('eligible');
            $received = (int) $rows->sum('received');

            return [$type->value => ['type' => $type, 'schedules' => $rows->count(), 'eligible' => $eligible, 'received' => $received, 'rate' => $eligible > 0 ? round($received / $eligible * 100, 1) : null]];
        })->filter(fn ($row) => $row['schedules'] > 0);

        $vendors = $schedules->groupBy(fn ($r) => $r['schedule']->vendor_id)->map(fn (Collection $rows) => [
            'vendor' => $rows->first()['schedule']->vendor,
            'schedules' => $rows->count(),
            'ordered' => (int) $rows->sum(fn ($r) => $r['schedule']->portions_ordered),
            'delivered' => collect(DeliveryUnit::cases())->mapWithKeys(fn (DeliveryUnit $unit) => [
                $unit->value => (int) $rows->filter(fn ($r) => $r['schedule']->delivery?->unit === $unit)->sum('delivered'),
            ])->all(),
            'received' => (int) $rows->sum('received'),
        ])->sortBy(fn ($row) => $row['vendor']?->name)->values();

        // Jumlah tanda terima per peserta per jenis konsumsi.
        $perParticipant = collect($ids)->mapWithKeys(fn ($id) => [$id => array_fill_keys(array_map(fn (MealType $t) => $t->value, MealType::cases()), 0)])->all();
        foreach ($schedules as $row) {
            foreach ($row['schedule']->receipts as $receipt) {
                $perParticipant[$receipt->user_id][$row['schedule']->meal_type->value]++;
            }
        }

        $eligible = (int) $schedules->sum('eligible');
        $received = (int) $schedules->sum('received');

        return [
            'schedules' => $schedules,
            'days' => $days,
            'types' => $types,
            'vendors' => $vendors,
            'participants' => collect($perParticipant),
            'staff' => $staff,
            'totals' => [
                'ordered' => (int) $schedules->sum(fn ($r) => $r['schedule']->portions_ordered),
                'delivered' => (int) $schedules->sum(fn ($r) => $r['delivered'] ?? 0),
                'received' => $received,
                'eligible' => $eligible,
                'proxied' => (int) $schedules->sum('proxied'),
                'rate' => $eligible > 0 ? round($received / $eligible * 100, 1) : null,
            ],
        ];
    }
}
