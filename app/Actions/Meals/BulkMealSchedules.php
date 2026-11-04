<?php

namespace App\Actions\Meals;

use App\Enums\MealType;
use App\Models\MealSchedule;
use App\Models\User;
use App\Services\MealRoster;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Isi dan ubah jadwal makan sekaligus untuk rentang tanggal (maks. 62 hari), agar tim konsumsi
 * tidak perlu membuat/mengubah jadwal satu per satu. Jadwal yang sudah ada penerimaannya tidak diubah.
 */
class BulkMealSchedules
{
    public const MAX_DAYS = 62;

    public const PLACEHOLDER_MENU = 'Menu menyusul';

    public function __construct(protected MealRoster $roster) {}

    /**
     * Tanggal dalam rentang yang harinya dipilih (1 = Senin … 7 = Minggu).
     *
     * @param  list<int|string>  $weekdays
     * @return Collection<int, CarbonImmutable>
     */
    public function dates(string $from, string $to, array $weekdays): Collection
    {
        $start = CarbonImmutable::parse($from)->startOfDay();
        $end = CarbonImmutable::parse($to)->startOfDay();

        if ($end->lt($start)) {
            throw new InvalidArgumentException('Tanggal akhir harus sama atau setelah tanggal mulai.');
        }

        if ($start->diffInDays($end) + 1 > self::MAX_DAYS) {
            throw new InvalidArgumentException('Rentang maksimal '.self::MAX_DAYS.' hari sekali proses.');
        }

        $days = array_map('intval', $weekdays);

        return collect(range(0, (int) $start->diffInDays($end)))
            ->map(fn (int $i) => $start->addDays($i))
            ->filter(fn (CarbonImmutable $d) => in_array($d->dayOfWeekIso, $days, true))
            ->values();
    }

    /**
     * Menu bergilir: satu baris = menu satu hari; bila baris habis, diulang dari awal.
     *
     * @return list<string>
     */
    public static function menuLines(?string $menu): array
    {
        return array_values(array_filter(array_map(fn ($line) => mb_substr(trim($line), 0, 500), preg_split('/\R/', (string) $menu)), fn ($line) => $line !== ''));
    }

    /**
     * Rencana isi massal: berapa dibuat, diperbarui, dilewati (untuk pratinjau).
     *
     * @param  array<string, array<string, mixed>>  $meals  jenis => vendor_id, start_time, end_time, portions_mode, portions, menu
     * @return array{create: int, update: int, skip: int, total: int}
     */
    public function preview(Collection $dates, array $meals, string $existing): array
    {
        $plan = ['create' => 0, 'update' => 0, 'skip' => 0, 'total' => 0];
        $current = $this->existing($dates, array_keys($meals));

        foreach ($meals as $type => $meal) {
            foreach ($dates as $date) {
                $plan['total']++;
                $decision = $this->decide($current->get($date->toDateString().'|'.$type, collect()), (int) $meal['vendor_id'], $existing);
                $plan[$decision[0]]++;
            }
        }

        return $plan;
    }

    /**
     * @param  array<string, array<string, mixed>>  $meals
     * @return array{create: int, update: int, skip: int, total: int}
     */
    public function fill(Collection $dates, array $meals, string $existing, User $by): array
    {
        $result = ['create' => 0, 'update' => 0, 'skip' => 0, 'total' => 0];

        DB::transaction(function () use ($dates, $meals, $existing, $by, &$result) {
            $current = $this->existing($dates, array_keys($meals));

            foreach ($meals as $type => $meal) {
                $mealType = MealType::from($type);
                $lines = self::menuLines($meal['menu'] ?? '') ?: [self::PLACEHOLDER_MENU];
                $portions = ($meal['portions_mode'] ?? 'auto') === 'auto'
                    ? $this->roster->recipients($mealType)->count()
                    : (int) $meal['portions'];

                foreach ($dates->values() as $i => $date) {
                    $result['total']++;
                    [$decision, $target] = $this->decide($current->get($date->toDateString().'|'.$type, collect()), (int) $meal['vendor_id'], $existing);
                    $starts = $date->setTimeFromTimeString($meal['start_time']);
                    $ends = $date->setTimeFromTimeString($meal['end_time']);

                    if ($ends->lte($starts)) {
                        throw new InvalidArgumentException($mealType->label().': jam selesai harus setelah jam mulai.');
                    }

                    $attributes = [
                        'vendor_id' => (int) $meal['vendor_id'],
                        'menu' => $lines[$i % count($lines)],
                        'starts_at' => $starts,
                        'ends_at' => $ends,
                        'portions_ordered' => $portions,
                    ];

                    match ($decision) {
                        'create' => ManageMealSchedules::createOrRestore($attributes + ['date' => $date, 'meal_type' => $mealType, 'created_by' => $by->id]),
                        'update' => $target->update($attributes),
                        default => null,
                    };
                    $result[$decision]++;
                }
            }

            activity('konsumsi')->causedBy($by)->withProperties($result + ['dari' => $dates->first()?->toDateString(), 'sampai' => $dates->last()?->toDateString(), 'jenis' => array_keys($meals)])
                ->log('Isi massal jadwal makan');
        });

        return $result;
    }

    /**
     * Jadwal yang cocok dengan filter ubah/hapus massal.
     *
     * @param  list<string>  $types
     */
    public function matching(Collection $dates, array $types, ?int $vendorId = null): Builder
    {
        return MealSchedule::query()
            ->whereIn('date', $dates->map->toDateString()->all())
            ->whereIn('meal_type', $types)
            ->when($vendorId, fn ($q) => $q->where('vendor_id', $vendorId))
            ->withCount('receipts')
            ->orderBy('date')->orderBy('starts_at');
    }

    /**
     * Ubah massal. $changes: vendor_id?, time_mode (set|shift)?, start_time, end_time, shift_minutes,
     * portions_mode (auto|fixed)?, portions, menu?. Hanya kunci yang ada yang diterapkan.
     *
     * @param  array<string, mixed>  $changes
     * @return array{updated: int, skipped: int, notes: list<string>}
     */
    public function change(Builder $query, array $changes, User $by): array
    {
        $result = ['updated' => 0, 'skipped' => 0, 'notes' => []];

        DB::transaction(function () use ($query, $changes, $by, &$result) {
            $lines = array_key_exists('menu', $changes) ? (self::menuLines($changes['menu']) ?: [self::PLACEHOLDER_MENU]) : [];
            $menuIndex = [];
            $autoPortions = [];

            foreach ($query->get() as $schedule) {
                if ($schedule->receipts_count > 0) {
                    $result['skipped']++;
                    $result['notes']['receipts'] = 'Jadwal yang sudah ada penerimaannya tidak diubah.';

                    continue;
                }

                $attributes = [];

                if (! empty($changes['vendor_id']) && (int) $changes['vendor_id'] !== $schedule->vendor_id) {
                    // Termasuk jadwal terhapus: kunci unik tanggal–jenis–vendor tetap berlaku.
                    $clash = MealSchedule::withTrashed()->whereDate('date', $schedule->date)->where('meal_type', $schedule->meal_type)
                        ->where('vendor_id', $changes['vendor_id'])->whereKeyNot($schedule->id)->exists();
                    if ($clash) {
                        $result['skipped']++;
                        $result['notes']['clash'] = 'Sebagian dilewati karena vendor tujuan sudah punya jadwal yang sama pada tanggal itu.';

                        continue;
                    }
                    $attributes['vendor_id'] = (int) $changes['vendor_id'];
                }

                if (($changes['time_mode'] ?? null) === 'set') {
                    $attributes['starts_at'] = $schedule->date->setTimeFromTimeString($changes['start_time']);
                    $attributes['ends_at'] = $schedule->date->setTimeFromTimeString($changes['end_time']);
                } elseif (($changes['time_mode'] ?? null) === 'shift') {
                    $minutes = (int) $changes['shift_minutes'];
                    $attributes['starts_at'] = $schedule->starts_at->addMinutes($minutes);
                    $attributes['ends_at'] = $schedule->ends_at->addMinutes($minutes);

                    if (! $attributes['starts_at']->isSameDay($schedule->date) || ! $attributes['ends_at']->isSameDay($schedule->date)) {
                        $result['skipped']++;
                        $result['notes']['day'] = 'Sebagian dilewati karena jam hasil geser melewati hari yang sama.';

                        continue;
                    }
                }

                if (($changes['portions_mode'] ?? null) === 'auto') {
                    $type = $schedule->meal_type->value;
                    $autoPortions[$type] ??= $this->roster->recipients($schedule->meal_type)->count();
                    $attributes['portions_ordered'] = $autoPortions[$type];
                } elseif (($changes['portions_mode'] ?? null) === 'fixed') {
                    $attributes['portions_ordered'] = (int) $changes['portions'];
                }

                if ($lines !== []) {
                    // Menu bergilir per jenis makan, urut tanggal.
                    $key = $schedule->meal_type->value;
                    $menuIndex[$key] = ($menuIndex[$key] ?? -1) + 1;
                    $attributes['menu'] = $lines[$menuIndex[$key] % count($lines)];
                }

                if ($attributes !== []) {
                    $schedule->update($attributes);
                    $result['updated']++;
                }
            }

            activity('konsumsi')->causedBy($by)->withProperties(['diubah' => $result['updated'], 'dilewati' => $result['skipped'], 'perubahan' => array_keys($changes)])
                ->log('Ubah massal jadwal makan');
        });

        $result['notes'] = array_values($result['notes']);

        return $result;
    }

    /**
     * Hapus massal jadwal yang belum ada penerimaannya.
     *
     * @return array{deleted: int, skipped: int}
     */
    public function delete(Builder $query, User $by): array
    {
        $result = ['deleted' => 0, 'skipped' => 0];

        DB::transaction(function () use ($query, $by, &$result) {
            foreach ($query->get() as $schedule) {
                if ($schedule->receipts_count > 0) {
                    $result['skipped']++;

                    continue;
                }

                $schedule->delete();
                $result['deleted']++;
            }

            activity('konsumsi')->causedBy($by)->withProperties($result)->log('Hapus massal jadwal makan');
        });

        return $result;
    }

    /**
     * Jadwal yang sudah ada, dikelompokkan per "tanggal|jenis".
     *
     * @param  list<string>  $types
     * @return Collection<string, Collection<int, MealSchedule>>
     */
    protected function existing(Collection $dates, array $types): Collection
    {
        if ($dates->isEmpty() || $types === []) {
            return collect();
        }

        return MealSchedule::query()->whereIn('date', $dates->map->toDateString()->all())->whereIn('meal_type', $types)
            ->withCount('receipts')->get()
            ->groupBy(fn (MealSchedule $s) => $s->date->toDateString().'|'.$s->meal_type->value);
    }

    /**
     * Keputusan untuk satu tanggal & jenis: buat baru, perbarui jadwal yang ada, atau lewati.
     *
     * @param  Collection<int, MealSchedule>  $current
     * @return array{0: 'create'|'update'|'skip', 1: MealSchedule|null}
     */
    protected function decide(Collection $current, int $vendorId, string $existing): array
    {
        if ($current->isEmpty()) {
            return ['create', null];
        }

        if ($existing !== 'update') {
            return ['skip', null];
        }

        // Perbarui jadwal vendor yang sama; bila tidak ada, satu-satunya jadwal di slot itu. Yang sudah ada penerimaan tidak diubah.
        $target = $current->firstWhere('vendor_id', $vendorId) ?? ($current->count() === 1 ? $current->first() : null);

        return $target && $target->receipts_count === 0 ? ['update', $target] : ['skip', null];
    }
}
