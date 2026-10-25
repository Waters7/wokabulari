<?php

namespace App\Notifications;

use App\Models\HealthReport;

/**
 * Tombol DARURAT: notifikasi prioritas tinggi ke semua petugas asrama & kesehatan (SPEC 3.18).
 */
class EmergencyAlertNotification extends WokabulariNotification
{
    protected string $icon = 'exclamation-triangle';

    protected string $level = 'danger';

    protected bool $viaWhatsApp = true;

    public function __construct(public HealthReport $report) {}

    public function title(): string
    {
        return 'DARURAT: '.$this->report->user->name;
    }

    public function body(): string
    {
        $user = $this->report->user;
        $where = ($this->report->dormitory?->name ?? 'Asrama ?').', kamar '.($this->report->room?->number ?? '?');
        $allergies = $user->profile?->hasAllergies() ? ' Alergi: '.$user->profile->allergies.'.' : '';

        return "Butuh bantuan segera di {$where}. HP: ".($user->phone ?: '-').'.'.$allergies;
    }

    public function url(): ?string
    {
        return route('health.show', $this->report);
    }
}
