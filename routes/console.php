<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Jadwal (WITA) — SPEC 7
|--------------------------------------------------------------------------
*/

Schedule::command('app:close-expired-codes')->everyMinute()->withoutOverlapping();
