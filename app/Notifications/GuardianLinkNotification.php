<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Hubungan wali–peserta: menunggu verifikasi (ke admin), diverifikasi/ditolak (ke wali).
 */
class GuardianLinkNotification extends WokabulariNotification
{
    public function __construct(public User $participant, public string $event, public ?User $guardian = null)
    {
        $this->icon = 'link';
        $this->level = match ($event) {
            'verified' => 'success',
            'rejected' => 'danger',
            default => 'info',
        };
        $this->viaWhatsApp = $event === 'verified';
    }

    public function title(): string
    {
        return match ($this->event) {
            'pending' => 'Verifikasi wali: '.$this->participant->name,
            'verified' => 'Akun wali terhubung dengan '.$this->participant->name,
            default => 'Penautan wali ditolak',
        };
    }

    public function body(): string
    {
        return match ($this->event) {
            'pending' => ($this->guardian?->name ?? 'Seorang wali').' ('.($this->guardian?->phone ?? '-').') minta ditautkan. Mohon periksa dan verifikasi.',
            'verified' => 'Bapak/Ibu sekarang bisa melihat kehadiran, nilai, dan kabar '.$this->participant->name.'.',
            default => 'Penautan dengan '.$this->participant->name.' tidak disetujui panitia. Hubungi admin program bila ada pertanyaan.',
        };
    }

    public function url(): ?string
    {
        $route = $this->event === 'pending' ? 'master.guardians' : 'dashboard';

        return Route::has($route) ? route($route) : null;
    }
}
