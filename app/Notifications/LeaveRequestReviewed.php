<?php

namespace App\Notifications;

use App\Enums\ReviewStatus;
use App\Models\LeaveRequest;

class LeaveRequestReviewed extends WokabulariNotification
{
    public function __construct(public LeaveRequest $leave)
    {
        $approved = $leave->status === ReviewStatus::Approved;
        $this->level = $approved ? 'success' : 'warning';
        $this->icon = $approved ? 'check-circle' : 'x-circle';
    }

    public function title(): string
    {
        return $this->leave->type->label().' '.strtolower($this->leave->status->label());
    }

    public function body(): string
    {
        $text = "Pengajuan {$this->leave->type->label()} tanggal {$this->leave->periodLabel()} {$this->leave->status->label()}.";

        return $this->leave->review_note ? $text.' Catatan: '.$this->leave->review_note : $text;
    }

    public function url(): ?string
    {
        return route('leave.index');
    }
}
