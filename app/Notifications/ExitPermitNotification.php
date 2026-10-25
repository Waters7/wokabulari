<?php

namespace App\Notifications;

use App\Models\DormExitPermit;
use Illuminate\Support\Facades\Route;

/**
 * Notifikasi alur izin keluar asrama. $event: guardian_needed, review_needed, approved, rejected,
 * guardian_info, late, returned.
 */
class ExitPermitNotification extends WokabulariNotification
{
    public function __construct(public DormExitPermit $permit, public string $event)
    {
        $this->icon = 'arrow-right-start-on-rectangle';
        $this->level = match ($event) {
            'approved', 'returned' => 'success',
            'rejected', 'late' => 'danger',
            default => 'warning',
        };
        // Persetujuan wali & keterlambatan dikirim juga lewat WhatsApp.
        $this->viaWhatsApp = in_array($event, ['guardian_needed', 'late', 'guardian_info'], true);
    }

    public function title(): string
    {
        $name = $this->permit->user->name;

        return match ($this->event) {
            'guardian_needed' => "Persetujuan izin keluar {$name}",
            'review_needed' => "Izin keluar baru: {$name}",
            'approved' => 'Izin keluar disetujui',
            'rejected' => 'Izin keluar ditolak',
            'guardian_info' => "Izin keluar {$name} ".($this->permit->status->value === 'rejected' ? 'ditolak' : 'disetujui'),
            'late' => "{$name} terlambat kembali ke asrama",
            'returned' => "{$name} sudah kembali ke asrama",
            default => 'Izin keluar asrama',
        };
    }

    public function body(): string
    {
        $permit = $this->permit;
        $info = $permit->type->label().' ke '.$permit->destination.' · '.$permit->periodLabel().' WITA';

        return match ($this->event) {
            'guardian_needed' => "{$info}. Alasan: {$permit->reason}. Mohon setujui atau tolak di portal wali.",
            'approved' => "{$info}. Tunjukkan QR izin ke petugas saat keluar dan kembali.".($permit->approval_note ? " Catatan: {$permit->approval_note}" : ''),
            'rejected' => 'Alasan: '.($permit->approval_note ?: $permit->guardian_note ?: '-'),
            'late' => 'Rencana kembali '.tanggal_jam($permit->planned_return).' WITA belum tercatat kembali. Tujuan: '.$permit->destination.', kontak '.$permit->contact_phone.'.',
            'returned' => 'Tercatat kembali pukul '.jam($permit->actual_return).' WITA.',
            default => $info,
        };
    }

    public function url(): ?string
    {
        $route = match ($this->event) {
            'guardian_needed', 'guardian_info' => 'guardian.approvals',
            'review_needed', 'late' => 'dorm.manage.exits',
            default => 'dorm.exit.index',
        };

        return Route::has($route) ? route($route) : null;
    }
}
