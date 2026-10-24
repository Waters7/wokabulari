<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Log;

/**
 * Driver lokal: pesan WhatsApp hanya dicatat di storage/logs.
 */
class LogWhatsAppGateway implements WhatsAppGateway
{
    public function send(string $phone, string $message): bool
    {
        Log::channel(config('logging.default'))->info('[WhatsApp] ke '.$phone, ['pesan' => $message]);

        return true;
    }
}
