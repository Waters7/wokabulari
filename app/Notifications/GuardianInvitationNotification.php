<?php

namespace App\Notifications;

use App\Models\GuardianInvitation;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Notifications\AnonymousNotifiable;

/**
 * Undangan pendaftaran wali (penerima belum punya akun: email/WhatsApp on-demand).
 */
class GuardianInvitationNotification extends WokabulariNotification
{
    protected string $icon = 'user-plus';

    public function __construct(public GuardianInvitation $invitation) {}

    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof AnonymousNotifiable) {
            return parent::via($notifiable);
        }

        return array_values(array_filter([
            filled($notifiable->routeNotificationFor('mail')) ? 'mail' : null,
            filled($notifiable->routeNotificationFor('whatsapp')) ? WhatsAppChannel::class : null,
        ]));
    }

    public function title(): string
    {
        return 'Undangan portal wali Wokabulari';
    }

    public function body(): string
    {
        return 'Bapak/Ibu diundang memantau perkembangan '.$this->invitation->participant->name.' di program SMK Go Global. '
            .'Daftar dengan kode '.$this->invitation->code.' (berlaku sampai '.tanggal($this->invitation->expires_at).').';
    }

    public function url(): ?string
    {
        return $this->invitation->url();
    }
}
