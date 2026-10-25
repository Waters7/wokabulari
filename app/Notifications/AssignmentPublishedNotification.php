<?php

namespace App\Notifications;

use App\Models\Assignment;

class AssignmentPublishedNotification extends WokabulariNotification
{
    protected string $icon = 'clipboard-document-check';

    public function __construct(public Assignment $assignment) {}

    public function title(): string
    {
        return 'Tugas baru: '.$this->assignment->title;
    }

    public function body(): string
    {
        return 'Tenggat '.tanggal_jam($this->assignment->due_at).' WITA. Ayo jo, kumpul sebelum tenggat!';
    }

    public function url(): ?string
    {
        return route('assignments.show', $this->assignment);
    }
}
