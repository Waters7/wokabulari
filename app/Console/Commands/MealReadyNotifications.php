<?php

namespace App\Console\Commands;

use App\Models\MealDelivery;
use App\Notifications\MealReadyNotification;
use App\Services\MealRoster;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

#[Signature('app:meal-ready-notifications')]
#[Description('Beri tahu peserta saat makanan yang diinput tim konsumsi siap diambil (jam & tempat), sekali per input.')]
class MealReadyNotifications extends Command
{
    public function handle(MealRoster $roster): int
    {
        $sent = 0;

        $due = MealDelivery::query()->with(['schedule.receipts'])
            ->whereNull('notified_at')
            ->where('ready_at', '<=', now())
            // Laporan terlambat lebih dari 3 jam tidak lagi diumumkan.
            ->where('ready_at', '>=', now()->subHours(3))
            ->get();

        foreach ($due as $delivery) {
            $schedule = $delivery->schedule;

            // Hanya penerima jenis konsumsi ini (aturan peserta tidak asrama) yang belum mengambil.
            $recipients = $roster->participants($schedule->meal_type)
                ->reject(fn ($participant) => $schedule->receipts->contains('user_id', $participant->id))
                ->values();

            Notification::send($recipients, new MealReadyNotification($delivery));
            $delivery->forceFill(['notified_at' => now()])->save();
            $sent += $recipients->count();
        }

        $this->info("{$sent} pemberitahuan makanan siap dikirim.");

        return self::SUCCESS;
    }
}
