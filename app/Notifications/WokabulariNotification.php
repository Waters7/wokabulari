<?php

namespace App\Notifications;

use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi dasar Wokabulari: selalu ke pusat notifikasi (database); email & WhatsApp opsional.
 * Dikirim lewat antrean "notifications".
 */
abstract class WokabulariNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** Kirim juga lewat email bila penerima punya alamat email. */
    protected bool $viaMail = false;

    /** Kirim juga lewat WhatsApp bila penerima punya nomor HP. */
    protected bool $viaWhatsApp = false;

    /** info | success | warning | danger */
    protected string $level = 'info';

    protected string $icon = 'bell';

    abstract public function title(): string;

    abstract public function body(): string;

    public function url(): ?string
    {
        return null;
    }

    public function viaQueues(): array
    {
        return ['database' => 'notifications', 'mail' => 'notifications', WhatsAppChannel::class => 'notifications'];
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($this->viaMail && filled($notifiable->email ?? null)) {
            $channels[] = 'mail';
        }

        if ($this->viaWhatsApp && filled($notifiable->phone ?? null)) {
            $channels[] = WhatsAppChannel::class;
        }

        return $channels;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'url' => $this->url(),
            'icon' => $this->icon,
            'level' => $this->level,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('WOKABULARI · '.$this->title())
            ->greeting('Halo, '.$notifiable->name.'!')
            ->line($this->body());

        if ($url = $this->url()) {
            $mail->action('Buka Wokabulari', $url);
        }

        return $mail->salutation('Salam, Tim Wokabulari');
    }

    public function toWhatsApp(object $notifiable): string
    {
        return "*WOKABULARI* · {$this->title()}\n{$this->body()}".($this->url() ? "\n{$this->url()}" : '');
    }
}
