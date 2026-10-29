<?php

namespace App\Actions\Meals;

use App\Events\MealReceived;
use App\Models\MealReceipt;
use App\Models\MealSchedule;
use App\Models\User;
use App\Services\ImageService;
use App\Services\MealRoster;
use InvalidArgumentException;

/**
 * Penerimaan makanan dengan tanda tangan wajib (SPEC 3.12).
 * - Peserta: hanya dalam rentang waktu pengambilan, sekali per jadwal.
 * - Diwakilkan: petugas mencatat atas nama peserta (tanda tangan petugas + catatan), sampai akhir hari.
 */
class ReceiveMeal
{
    public function __construct(protected ImageService $images, protected MealRoster $roster) {}

    public function handle(MealSchedule $schedule, User $participant, string $signatureDataUri, ?User $proxy = null, ?string $note = null, ?float $latitude = null, ?float $longitude = null): MealReceipt
    {
        if (! $participant->receivesMeals()) {
            throw new InvalidArgumentException('Penerima harus peserta aktif atau tim yang ditandai menerima konsumsi.');
        }

        if (! $this->roster->isEligible($participant, $schedule->meal_type)) {
            throw new InvalidArgumentException($schedule->meal_type->label().' tidak termasuk konsumsi '.($participant->isParticipant() ? 'peserta tidak asrama' : 'tim pengajar/tim kerja').'.');
        }

        if ($proxy) {
            if (now()->lt($schedule->starts_at) || now()->gt($schedule->date->endOfDay())) {
                throw new InvalidArgumentException('Penerimaan diwakilkan hanya bisa dicatat sejak jadwal dibuka sampai akhir hari.');
            }

            if (blank($note)) {
                throw new InvalidArgumentException('Catatan wajib diisi untuk penerimaan diwakilkan (mis. sakit, diantar ke kamar).');
            }
        } elseif (! $schedule->isOpen()) {
            throw new InvalidArgumentException('Di luar waktu pengambilan ('.$schedule->windowLabel().' WITA).');
        }

        if (MealReceipt::query()->where('meal_schedule_id', $schedule->id)->where('user_id', $participant->id)->exists()) {
            throw new InvalidArgumentException('Penerimaan untuk jadwal ini sudah tercatat.');
        }

        $path = $this->images->storeSignature($signatureDataUri, 'signatures/'.$schedule->date->format('Y/m'));

        $receipt = MealReceipt::query()->create([
            'meal_schedule_id' => $schedule->id,
            'user_id' => $participant->id,
            'signature_path' => $path,
            'received_at' => now(),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'proxy_by' => $proxy?->id,
            'note' => $note ? trim($note) : null,
        ]);

        event(new MealReceived($receipt));

        return $receipt;
    }
}
