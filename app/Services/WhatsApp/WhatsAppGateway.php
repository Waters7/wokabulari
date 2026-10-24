<?php

namespace App\Services\WhatsApp;

/**
 * Gateway WhatsApp yang dapat diganti lewat WHATSAPP_DRIVER (log untuk lokal, gateway pihak ketiga untuk produksi).
 */
interface WhatsAppGateway
{
    public function send(string $phone, string $message): bool;
}
