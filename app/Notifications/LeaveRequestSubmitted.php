<?php

namespace App\Notifications;

use App\Models\LeaveRequest;

class LeaveRequestSubmitted extends WokabulariNotification
{
    protected string $icon = 'document-text';

    public function __construct(public LeaveRequest $leave) {}

    public function title(): string
    {
        return 'Pengajuan '.$this->leave->type->label().' baru';
    }

    public function body(): string
    {
        return "{$this->leave->user->name} mengajukan {$this->leave->type->label()} ({$this->leave->periodLabel()}): {$this->leave->reason}";
    }

    public function url(): ?string
    {
        return route('manage.leave.index');
    }
}
