<?php

namespace App\Events;

use App\Models\LearningSession;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Peserta ditandai alpa pada sesi (otomatis saat sesi selesai).
 */
class ParticipantMarkedAbsent
{
    use Dispatchable, SerializesModels;

    public function __construct(public User $participant, public LearningSession $session) {}
}
