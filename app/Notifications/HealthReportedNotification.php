<?php

namespace App\Notifications;

use App\Models\HealthReport;
use Illuminate\Support\Facades\Route;

/**
 * Laporan sakit baru ke pengelola asrama, tim kesehatan, dan wali. Berat/darurat dikirim juga via WhatsApp.
 */
class HealthReportedNotification extends WokabulariNotification
{
    public function __construct(public HealthReport $report, public string $audience = 'staff')
    {
        $this->icon = 'heart';
        $this->level = $report->severity->isUrgent() ? 'danger' : 'warning';
        $this->viaWhatsApp = $report->severity->isUrgent();
    }

    public function title(): string
    {
        $prefix = $this->report->is_emergency ? 'DARURAT: ' : '';

        return $prefix.$this->report->user->name.' sakit ('.$this->report->severity->label().')';
    }

    public function body(): string
    {
        $report = $this->report;
        $where = trim(($report->dormitory?->name ?? '').($report->room ? ' · kamar '.$report->room->number : ''), ' ·');
        $symptoms = implode(', ', $report->symptomLabels()) ?: 'gejala belum diisi';

        return $this->audience === 'guardian'
            ? "Anak Bapak/Ibu melaporkan sakit: {$symptoms}. Petugas asrama dan kesehatan sudah diberi tahu dan akan menangani."
            : "Gejala: {$symptoms}".($report->temperature ? ", suhu {$report->temperature}°C" : '').($where ? ". {$where}" : '').'.';
    }

    public function url(): ?string
    {
        if ($this->audience === 'guardian') {
            return Route::has('guardian.child') ? route('guardian.child') : null;
        }

        return route('health.show', $this->report);
    }
}
