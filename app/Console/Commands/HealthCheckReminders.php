<?php

namespace App\Console\Commands;

use App\Enums\StaffUnit;
use App\Models\HealthReportAction;
use App\Models\User;
use App\Notifications\HealthCheckReminderNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

#[Signature('app:health-check-reminders')]
#[Description('Ingatkan petugas jadwal pemantauan laporan sakit (next_check_at) dalam 1 jam ke depan.')]
class HealthCheckReminders extends Command
{
    public function handle(): int
    {
        $actions = HealthReportAction::query()->with(['report.user', 'handler'])
            ->whereNull('reminded_at')
            ->whereNotNull('next_check_at')
            ->where('next_check_at', '<=', now()->addHour())
            ->whereHas('report', fn ($q) => $q->open())
            ->get();

        $healthStaff = User::query()->inUnits(StaffUnit::Health)->get();

        foreach ($actions as $action) {
            $recipients = $action->handler ? collect([$action->handler])->merge($healthStaff)->unique('id') : $healthStaff;
            Notification::send($recipients, new HealthCheckReminderNotification($action));
            $action->update(['reminded_at' => now()]);
        }

        $this->info($actions->count().' pengingat pemantauan dikirim.');

        return self::SUCCESS;
    }
}
