<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Peserta & wali wajib menyetujui pemrosesan data pribadi (UU PDP) sebelum memakai aplikasi.
 */
class EnsureConsent
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->needsConsent()) {
            if ($request->expectsJson()) {
                abort(403, 'Persetujuan data pribadi belum diberikan.');
            }

            return redirect()->route('consent.show');
        }

        return $next($request);
    }
}
