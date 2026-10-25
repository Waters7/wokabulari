<?php

namespace App\Listeners;

use App\Events\ParticipantMarkedAbsent;
use App\Notifications\ChildAbsentNotification;
use Illuminate\Support\Facades\Notification;

class NotifyGuardiansOfAbsence
{
    public function handle(ParticipantMarkedAbsent $event): void
    {
        Notification::send($event->participant->guardians()->get(), new ChildAbsentNotification($event->participant, $event->session));
    }
}
