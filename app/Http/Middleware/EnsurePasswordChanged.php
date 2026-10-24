<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Akun dengan kata sandi awal (hasil import/admin) wajib mengganti kata sandi sebelum memakai aplikasi.
 */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password) {
            if ($request->expectsJson()) {
                abort(403, 'Kata sandi awal wajib diganti terlebih dahulu.');
            }

            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
