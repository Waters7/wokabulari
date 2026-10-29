<?php

namespace App\Actions\Meals;

use App\Enums\DeliveryUnit;
use App\Enums\MealType;
use App\Models\MealDelivery;
use App\Models\MealSchedule;
use App\Models\User;
use App\Services\ImageService;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Vendor melaporkan pengantaran: jumlah, menu, tanggal, jam siap diambil, tempat, dan foto.
 * Laporan menempel ke jadwal makan tim konsumsi (atau membuat jadwal bila belum ada);
 * pengambilan peserta dibuka mulai jam siap.
 */
class ReportMealDelivery
{
    public function __construct(protected ImageService $images) {}

    /**
     * @param  array{meal_schedule_id?: ?int, date?: string, meal_type?: string, ready_time: string, quantity: int, unit: string, menu: string, location: string}  $data
     */
    public function handle(User $reporter, array $data, ?UploadedFile $menuPhoto = null, ?UploadedFile $totalPhoto = null): MealDelivery
    {
        $vendor = $reporter->vendor;

        if (! $reporter->isVendor() || ! $vendor?->is_active) {
            throw new InvalidArgumentException('Akun ini tidak terhubung ke vendor aktif.');
        }

        $schedule = $this->findSchedule($vendor->id, $data);
        $delivery = $schedule?->delivery;

        if ($schedule && $schedule->date->lt(today())) {
            throw new InvalidArgumentException('Pengantaran hari yang sudah lewat tidak bisa diubah.');
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
                ->withProperties(['jadwal' => $type->label().' '.$date->toDateString(), 'jumlah' => $delivery->quantityLabel()])
                ->log('Vendor melaporkan pengantaran makanan');

            return $delivery->load('schedule');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function findSchedule(int $vendorId, array $data): ?MealSchedule
    {
        if (! empty($data['meal_schedule_id'])) {
            return MealSchedule::query()->where('vendor_id', $vendorId)->with('delivery')->findOrFail($data['meal_schedule_id']);
        }

        return MealSchedule::query()->where('vendor_id', $vendorId)->with('delivery')
            ->whereDate('date', $data['date'])->where('meal_type', $data['meal_type'])->first();
    }

    protected function deleteOld(?string $path): void
    {
        if ($path) {
            Storage::disk('private')->delete($path);
        }
    }
}
