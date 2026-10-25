<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Jadwal (WITA) — SPEC 7
|--------------------------------------------------------------------------
*/

Schedule::command('app:close-expired-codes')->everyMinute()->withoutOverlapping();
Schedule::command('app:assignment-reminders 2h')->everyThirtyMinutes()->withoutOverlapping();
Schedule::command('app:assignment-reminders day')->dailyAt('18:00');
