<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Peserta wajib mengisi nama & No. HP orang tua/wali sebelum memakai aplikasi.
 */
class EnsureGuardianContact
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isParticipant() && ! $user->profile?->hasGuardianContact()) {
            if ($request->expectsJson()) {
                abort(403, 'No. HP orang tua/wali belum diisi.');
            }

            return redirect()->route('guardian-contact.edit');
        }

        return $next($request);
    }
}
