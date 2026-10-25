<?php

namespace App\Actions\Users;

use App\Http\Requests\LoginEmailRequest;
use App\Models\User;
use InvalidArgumentException;

/**
 * Simpan email login milik pengguna sendiri (bukan super admin).
 */
class UpdateLoginEmail
{
    public function handle(User $user, ?string $email): User
    {
        $email = LoginEmailRequest::normalize($email);

        if ($email === $user->email) {
            return $user;
        }

        // Jangan sampai akun tidak punya cara login sama sekali.
        if ($email === null && blank($user->phone) && blank($user->username)) {
            throw new InvalidArgumentException('Isi email atau No. HP supaya ngana tetap bisa login.');
        }

        $user->forceFill(['email' => $email, 'email_verified_at' => null])->save();

        return $user;
    }
}
