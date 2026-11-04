<?php

namespace App\Actions\Meals;

use App\Enums\MealType;
use App\Models\MealSchedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Jadwal makan per hari (sarapan, makan siang, makan malam) dan duplikasi mingguan.
 */
class ManageMealSchedules
{
    /**
     * @param  array{date: string, meal_type: string, vendor_id: int|string, menu: string, start_time: string, end_time: string, portions_ordered: int|string}  $data
     */
    public function save(array $data, User $by, ?MealSchedule $schedule = null): MealSchedule
    {
        $date = CarbonImmutable::parse($data['date'])->startOfDay();
        $starts = $date->setTimeFromTimeString($data['start_time']);
        $ends = $date->setTimeFromTimeString($data['end_time']);

        if ($ends->lte($starts)) {
            throw new InvalidArgumentException('Jam selesai harus setelah jam mulai.');
        }

        $duplicate = MealSchedule::query()->whereDate('date', $date)->where('meal_type', $data['meal_type'])->where('vendor_id', $data['vendor_id'])
            ->when($schedule, fn ($q) => $q->whereKeyNot($schedule->id))->exists();

        if ($duplicate) {
            throw new InvalidArgumentException('Jadwal untuk vendor dan waktu makan ini sudah ada pada tanggal tersebut.');
        }

        $attributes = [
            'date' => $date,
            'meal_type' => $data['meal_type'],
            'vendor_id' => (int) $data['vendor_id'],
            'menu' => trim($data['menu']),
            'starts_at' => $starts,
            'ends_at' => $ends,
            'portions_ordered' => (int) $data['portions_ordered'],
        ];

        if (! $schedule) {
            return self::createOrRestore($attributes + ['created_by' => $by->id]);
        }

        $schedule->fill($attributes)->save();

        return $schedule;
    }

    /**
     * Buat jadwal baru; bila jadwal tanggal–jenis–vendor yang sama pernah dihapus, pulihkan saja
     * (kunci unik tabel juga berlaku untuk baris terhapus).
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function createOrRestore(array $attributes): MealSchedule
    {
        $trashed = MealSchedule::onlyTrashed()->whereDate('date', $attributes['date'])
            ->where('meal_type', $attributes['meal_type'] instanceof MealType ? $attributes['meal_type']->value : $attributes['meal_type'])
            ->where('vendor_id', $attributes['vendor_id'])->first();

        if ($trashed) {
            $trashed->restore();
            $trashed->update(collect($attributes)->except('created_by')->all());

            return $trashed;
        }

        return MealSchedule::query()->create($attributes);
    }

    /**
     * Salin jadwal makan utama satu minggu ke minggu berikutnya (jadwal yang sudah ada dilewati).
     * Snack tidak ikut disalin: tim konsumsi menambahkannya sendiri bila tersedia.
     */
    public function duplicateWeek(CarbonInterface $weekStart, User $by): int
    {
        $start = CarbonImmutable::parse($weekStart)->startOfWeek();
        $end = $start->endOfWeek();
        $created = 0;

        DB::transaction(function () use ($start, $end, $by, &$created) {
            foreach (MealSchedule::query()->whereDateBetween('date', $start->toDateString(), $end->toDateString())->get() as $schedule) {
                if ($schedule->meal_type->isSnack()) {
                    continue;
                }

                $date = $schedule->date->addWeek();
                $exists = MealSchedule::query()->whereDate('date', $date)->where('meal_type', $schedule->meal_type)->where('vendor_id', $schedule->vendor_id)->exists();

                if ($exists) {
                    continue;
                }

                self::createOrRestore([
                    'date' => $date,
                    'meal_type' => $schedule->meal_type,
                    'vendor_id' => $schedule->vendor_id,
                    'menu' => $schedule->menu,
                    'starts_at' => $schedule->starts_at->addWeek(),
                    'ends_at' => $schedule->ends_at->addWeek(),
                    'portions_ordered' => $schedule->portions_ordered,
                    'created_by' => $by->id,
                ]);
                $created++;
            }
        });

        return $created;
    }
}
