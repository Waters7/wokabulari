<?php

namespace App\Console\Commands;

use App\Notifications\RatingReminderNotification;
use App\Services\MealRoster;
use App\Services\RatingTargets;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:rating-reminders')]
#[Description('Ingatkan peserta yang belum mengisi rating vendor/pengajar minggu ini (Jumat 15.00).')]
class SendRatingReminders extends Command
{
    public function handle(MealRoster $roster, RatingTargets $targets): int
    {
        $sent = 0;

        foreach ($roster->participants() as $participant) {
            $pending = $targets->pendingCount($participant);

            if ($pending > 0) {
                $participant->notify(new RatingReminderNotification($pending));
                $sent++;
            }
        }

        $this->info("{$sent} pengingat rating dikirim.");

        return self::SUCCESS;
    }
}
