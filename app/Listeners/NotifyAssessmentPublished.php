<?php

namespace App\Listeners;

use App\Events\AssessmentPublished;
use App\Notifications\AssessmentPublishedNotification;

class NotifyAssessmentPublished
{
    public function handle(AssessmentPublished $event): void
    {
        $event->assessment->user->notify(new AssessmentPublishedNotification($event->assessment));
    }
}
