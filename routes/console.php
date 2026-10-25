<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Jadwal (WITA) — SPEC 7
|--------------------------------------------------------------------------
*/

Schedule::command('app:close-expired-codes')->everyMinute()->withoutOverlapping();
Schedule::command('app:submit-expired-attempts')->everyMinute()->withoutOverlapping();
Schedule::command('app:assignment-reminders 2h')->everyThirtyMinutes()->withoutOverlapping();
Schedule::command('app:assignment-reminders day')->dailyAt('18:00');
Schedule::command('app:rating-reminders')->fridays()->at('15:00');
Schedule::command('app:check-late-returns')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('app:dorm-attendance-alerts')->everyFifteenMinutes()->between('19:00', '23:59')->withoutOverlapping();
Schedule::command('app:health-check-reminders')->hourly();
Schedule::command('app:close-dorm-night')->dailyAt('00:05');
Schedule::command('app:update-streaks')->dailyAt('00:05');
Schedule::command('app:weekly-champions')->mondays()->at('06:00');
