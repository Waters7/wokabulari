<?php

namespace App\Notifications;

use App\Models\HealthReportAction;

class HealthCheckReminderNotification extends WokabulariNotification
{
    protected string $icon = 'clock';

    protected string $level = 'warning';

    public function __construct(public HealthReportAction $action) {}

    public function title(): string
    {
        return 'Jadwal pantau: '.$this->action->report->user->name;
    }

    public function body(): string
    {
        return 'Pemantauan kondisi dijadwalkan pukul '.jam($this->action->next_check_at).' WITA.';
    }

    public function url(): ?string
    {
        return route('health.show', $this->action->report);
    }
}
