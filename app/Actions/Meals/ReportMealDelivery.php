<?php

namespace App\Actions\Meals;

use App\Enums\DeliveryUnit;
use App\Enums\MealType;
use App\Models\MealDelivery;
use App\Models\MealSchedule;
use App\Models\User;
use App\Models\Vendor;
use App\Services\ImageService;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Tim konsumsi mencatat makanan yang datang: vendor, jumlah, menu, tanggal, jam siap diambil, tempat, dan foto.
 * Catatan menempel ke jadwal makan pada tanggal & waktu makan itu (atau membuat jadwal bila belum ada);
 * pengambilan peserta dibuka mulai jam siap. Snack hanya ada bila tim konsumsi menambahkannya.
 */
class ReportMealDelivery
{
    /** Batas hari ke belakang untuk input susulan. */
    public const BACKDATE_DAYS = 7;

    public function __construct(protected ImageService $images) {}

    /**
     * @param  array{meal_schedule_id?: ?int, vendor_id: int, date?: string, meal_type?: string, ready_time: string, quantity: int, unit: string, menu: string, location: string}  $data
     */
    public function handle(User $reporter, array $data, ?UploadedFile $menuPhoto = null, ?UploadedFile $totalPhoto = null): MealDelivery
    {
        if (! $reporter->can('meal.manage')) {
            throw new InvalidArgumentException('Hanya tim konsumsi yang dapat menginput konsumsi.');
        }

        $vendor = Vendor::query()->active()->find($data['vendor_id'] ?? null);
        if (! $vendor) {
            throw new InvalidArgumentException('Pilih vendor yang masih aktif.');
        }

        $schedule = $this->findSchedule($vendor->id, $data);
        $delivery = $schedule?->delivery;

        if ($schedule && $schedule->date->lt(today()->subDays(self::BACKDATE_DAYS))) {
            throw new InvalidArgumentException('Konsumsi lebih dari '.self::BACKDATE_DAYS.' hari lalu tidak bisa diubah.');
        }

        if ($schedule && $schedule->vendor_id !== $vendor->id && MealSchedule::query()->whereDate('date', $schedule->date)
            ->where('meal_type', $schedule->meal_type)->where('vendor_id', $vendor->id)->exists()) {
            throw new InvalidArgumentException('Sudah ada '.$schedule->meal_type->label().' dari '.$vendor->name.' pada tanggal ini.');
        }

        if (! $delivery && (! $menuPhoto || ! $totalPhoto)) {
            throw new InvalidArgumentException('Foto menu dan foto total makanan wajib dilampirkan.');
        }

        $type = $schedule?->meal_type ?? MealType::from($data['meal_type']);
        $date = CarbonImmutable::parse($schedule?->date ?? $data['date']);
        $readyAt = $date->setTimeFromTimeString($data['ready_time']);
        $menu = trim($data['menu']);

        return DB::transaction(function () use ($reporter, $vendor, $schedule, $delivery, $type, $date, $readyAt, $menu, $data, $menuPhoto, $totalPhoto) {
            [$windowStart, $windowEnd] = $type->defaultWindow();
            $window = (int) $date->setTimeFromTimeString($windowStart)->diffInMinutes($date->setTimeFromTimeString($windowEnd));

            if (! $schedule) {
                $schedule = MealSchedule::query()->create([
                    'date' => $date->toDateString(),
                    'meal_type' => $type,
                    'vendor_id' => $vendor->id,
                    'menu' => $menu,
                    'starts_at' => $readyAt,
                    'ends_at' => $readyAt->addMinutes($window),
                    'portions_ordered' => (int) $data['quantity'],
                    'created_by' => $reporter->id,
                ]);
            } else {
                // Pengambilan dibuka saat makanan siap; lama rentang pengambilan dipertahankan.
                $duration = max($window, (int) $schedule->starts_at->diffInMinutes($schedule->ends_at));
                $schedule->update([
                    'vendor_id' => $vendor->id,
                    'menu' => $menu,
                    'starts_at' => $readyAt,
                    'ends_at' => $schedule->ends_at->gt($readyAt->addMinutes($duration)) ? $schedule->ends_at : $readyAt->addMinutes($duration),
                ]);
            }

            $folder = 'meal-deliveries/'.$date->format('Y/m');
            $attributes = [
                'vendor_id' => $vendor->id,
                'reported_by' => $reporter->id,
                'quantity' => (int) $data['quantity'],
                'unit' => DeliveryUnit::from($data['unit']),
                'menu' => $menu,
                'ready_at' => $readyAt,
                'location' => trim($data['location']),
            ];

            if ($menuPhoto) {
                $attributes['menu_photo_path'] = $this->images->storeUpload($menuPhoto, $folder, 1600, 75);
                $this->deleteOld($delivery?->menu_photo_path);
            }

            if ($totalPhoto) {
                $attributes['total_photo_path'] = $this->images->storeUpload($totalPhoto, $folder, 1600, 75);
                $this->deleteOld($delivery?->total_photo_path);
            }

            // Jam siap diundur sebelum pemberitahuan terkirim: pemberitahuan menunggu jam yang baru.
            $delivery = MealDelivery::query()->updateOrCreate(['meal_schedule_id' => $schedule->id], $attributes);

            activity('konsumsi')->causedBy($reporter)->performedOn($delivery)
                ->withProperties(['jadwal' => $type->label().' '.$date->toDateString(), 'vendor' => $vendor->name, 'jumlah' => $delivery->quantityLabel()])
                ->log('Tim konsumsi menginput konsumsi');

            return $delivery->load(['schedule', 'vendor']);
        });
    }

    /**
     * Jadwal pada tanggal & waktu makan yang sama; bila ada beberapa, utamakan vendor terpilih.
     *
     * @param  array<string, mixed>  $data
     */
    protected function findSchedule(int $vendorId, array $data): ?MealSchedule
    {
        if (! empty($data['meal_schedule_id'])) {
            return MealSchedule::query()->with('delivery')->findOrFail($data['meal_schedule_id']);
        }

        return MealSchedule::query()->with('delivery')
            ->whereDate('date', $data['date'])->where('meal_type', $data['meal_type'])
            ->orderByRaw('vendor_id = ? desc', [$vendorId])->orderBy('id')->first();
    }

    protected function deleteOld(?string $path): void
    {
        if ($path) {
            Storage::disk('private')->delete($path);
        }
    }
}
