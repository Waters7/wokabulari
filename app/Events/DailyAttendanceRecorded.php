<?php

namespace App\Events;

use App\Models\DailyAttendance;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DailyAttendanceRecorded
{
    use Dispatchable, SerializesModels;

    public function __construct(public DailyAttendance $attendance) {}
}
