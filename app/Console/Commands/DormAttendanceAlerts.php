<?php

namespace App\Console\Commands;

use App\Enums\StaffUnit;
use App\Models\Dormitory;
use App\Models\User;
use App\Notifications\DormAbsenceNotification;
use App\Services\DormRoster;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

#[Signature('app:dorm-attendance-alerts')]
#[Description('Kirim daftar peserta yang belum absen asrama ke pengelola; setelah jam malam kabari wali (sekali per malam).')]
class DormAttendanceAlerts extends Command
{
    public function handle(DormRoster $roster): int
    {
        $managers = User::query()->inUnits(StaffUnit::Dormitory)->get();

        foreach (Dormitory::query()->get() as $dormitory) {
            [, $windowEnd] = $dormitory->checkWindow();
            $curfew = $dormitory->curfewAt();

            // Hanya sejak jendela absen ditutup sampai 2 jam setelah jam malam.
            if (now()->lt($windowEnd) || now()->gt($curfew->copy()->addHours(2))) {
                continue;
            }

            $missing = $roster->missing($dormitory, today());
            if ($missing->isEmpty()) {
                continue;
            }

            $afterCurfew = now()->gte($curfew);
            $names = $missing->map(fn ($row) => $row['user']->name)->all();

            // Ke pengelola: hanya bila daftar berubah sejak kiriman terakhir.
            $signature = 'dorm-alert:'.$dormitory->id.':'.today()->toDateString().':'.($afterCurfew ? 'curfew' : 'window');
            $hash = md5(implode('|', $names));
            if (Cache::get($signature) !== $hash) {
                Notification::send($managers, new DormAbsenceNotification($dormitory, $names, 'staff', $afterCurfew));
                Cache::put($signature, $hash, now()->addDay());
            }

            // Ke wali: sekali per peserta per malam, setelah jam malam.
            if ($afterCurfew && setting('dorm.notify_guardian_absent', true)) {
                foreach ($missing as $row) {
                    $key = 'dorm-guardian:'.$row['user']->id.':'.today()->toDateString();
                    if (Cache::add($key, true, now()->addDay())) {
                        Notification::send($row['user']->guardians()->get(), new DormAbsenceNotification($dormitory, [$row['user']->name], 'guardian', true));
                    }
                }
            }
        }

        return self::SUCCESS;
    }
}
