<?php

namespace App\Http\Middleware;

use App\Support\Haki;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan dasar. Kamera, mikrofon, dan lokasi hanya untuk origin sendiri.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Permissions-Policy', 'camera=(self), microphone=(self), geolocation=(self)');
        // Sidik HaKI: menandai salinan aplikasi ini di mana pun dijalankan.
        $response->headers->set('X-Wokabulari-HaKI', Haki::fingerprint());

        return $response;
    }
}
