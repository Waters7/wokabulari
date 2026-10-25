<?php

namespace App\Events;

use App\Models\DormAttendance;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Absen asrama peserta dikonfirmasi pengelola.
 */
class DormAttendanceConfirmed
{
    use Dispatchable, SerializesModels;

    public function __construct(public DormAttendance $attendance) {}
}
