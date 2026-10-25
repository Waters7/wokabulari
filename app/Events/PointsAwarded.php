<?php

namespace App\Events;

use App\Models\PointTransaction;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Poin peserta berubah (dipakai untuk cek naik level & lencana).
 */
class PointsAwarded
{
    use Dispatchable;

    public function __construct(public PointTransaction $transaction) {}
}
