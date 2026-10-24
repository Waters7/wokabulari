<?php

namespace App\Events;

use App\Models\SessionAttendance;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SessionAttendanceRecorded
{
    use Dispatchable, SerializesModels;

    public function __construct(public SessionAttendance $attendance) {}
}
