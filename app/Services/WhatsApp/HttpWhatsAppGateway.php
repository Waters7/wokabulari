<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Driver HTTP generik untuk gateway WhatsApp pihak ketiga (mis. Fonnte/Wablas/Qontak).
 * Format permintaan: POST {WHATSAPP_API_URL} dengan header Authorization dan body target + message.
 * Sesuaikan bila gateway yang dipilih memakai format berbeda.
 */
class HttpWhatsAppGateway implements WhatsAppGateway
{
    public function __construct(
        protected string $url,
        protected string $apiKey,
    ) {}

    public function send(string $phone, string $message): bool
    {
        try {
            $response = Http::timeout(15)
                ->withHeaders(['Authorization' => $this->apiKey])
                ->asForm()
                ->post($this->url, ['target' => $phone, 'message' => $message]);

            if ($response->failed()) {
                Log::warning('[WhatsApp] gagal kirim', ['status' => $response->status(), 'phone' => $phone]);
            }

            return $response->successful();
        } catch (Throwable $e) {
            Log::warning('[WhatsApp] error: '.$e->getMessage());

            return false;
        }
    }
}
