<?php

namespace App\Notifications;

use App\Models\Assignment;

class AssignmentReminderNotification extends WokabulariNotification
{
    protected string $icon = 'clock';

    protected string $level = 'warning';

    protected bool $viaWhatsApp = true;

    public function __construct(public Assignment $assignment, public string $window) {}

    public function title(): string
    {
        return $this->window === 'day' ? 'Tenggat tugas besok' : 'Tenggat tugas 2 jam lagi';
    }

    public function body(): string
    {
        return "{$this->assignment->title} — tenggat ".tanggal_jam($this->assignment->due_at).' WITA. Ayo jo, kumpul sebelum tenggat!';
    }

    public function url(): ?string
    {
        return route('assignments.show', $this->assignment);
    }
}
