<?php

namespace App\Listeners;

use App\Enums\PeriodType;
use App\Events\AssessmentPublished;
use App\Notifications\GuardianAssessmentNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Nilai bulanan yang boleh dilihat wali dikabarkan ke wali terverifikasi.
 */
class NotifyGuardiansAssessmentPublished
{
    public function handle(AssessmentPublished $event): void
    {
        $assessment = $event->assessment;

        if ($assessment->period_type !== PeriodType::Monthly || ! $assessment->visible_to_guardian) {
            return;
        }

        Notification::send($assessment->user->guardians()->get(), new GuardianAssessmentNotification($assessment));
    }
}
