<?php

namespace App\Events;

use App\Models\DormExitPermit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Izin keluar melewati rencana kembali (status late_return).
 */
class ExitPermitReturnedLate
{
    use Dispatchable, SerializesModels;

    public function __construct(public DormExitPermit $permit) {}
}
