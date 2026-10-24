<?php

namespace App\Notifications\Channels;

use App\Services\WhatsApp\WhatsAppGateway;
use Illuminate\Notifications\Notification;

class WhatsAppChannel
{
    public function __construct(protected WhatsAppGateway $gateway) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $phone = $notifiable->routeNotificationFor('whatsapp', $notification) ?? ($notifiable->phone ?? null);

        if (blank($phone) || ! method_exists($notification, 'toWhatsApp')) {
            return;
        }

        $this->gateway->send($phone, $notification->toWhatsApp($notifiable));
    }
}
