<?php

namespace App\Actions\Vendors;

use App\Enums\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Support\Phone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Akun login vendor makanan: dibuat tim konsumsi dari halaman Master → Vendor.
 */
class ManageVendorAccounts
{
    /**
     * @param  array{name: string, phone: string, email?: ?string, password: string}  $data
     */
    public function create(Vendor $vendor, array $data, User $actor): User
    {
        $phone = Phone::normalize($data['phone']);

        if (User::query()->where('phone', $phone)->exists()) {
            throw new InvalidArgumentException('No. HP sudah dipakai akun lain.');
        }

        $user = DB::transaction(function () use ($vendor, $data, $phone) {
            $user = new User([
                'name' => trim($data['name']),
                'phone' => $phone,
                'email' => filled($data['email'] ?? null) ? Str::lower(trim($data['email'])) : null,
                'password' => $data['password'],
                'is_active' => true,
                'must_change_password' => true,
            ]);
            $user->vendor_id = $vendor->id;
            $user->save();
            $user->assignRole(Role::Vendor->value);

            return $user;
        });

        activity('konsumsi')->causedBy($actor)->performedOn($vendor)
            ->withProperties(['akun' => $user->name])
            ->log('Membuat akun vendor');

        return $user;
    }

    public function resetPassword(User $account, string $password, User $actor): void
    {
        $this->ensureVendorAccount($account);
        $account->forceFill(['password' => $password, 'must_change_password' => true])->save();

        activity('konsumsi')->causedBy($actor)->performedOn($account)->log('Mengatur ulang kata sandi akun vendor');
    }

    public function toggleActive(User $account, User $actor): bool
    {
        $this->ensureVendorAccount($account);
        $account->forceFill(['is_active' => ! $account->is_active])->save();

        activity('konsumsi')->causedBy($actor)->performedOn($account)
            ->log($account->is_active ? 'Mengaktifkan akun vendor' : 'Menonaktifkan akun vendor');

        return $account->is_active;
    }

    protected function ensureVendorAccount(User $account): void
    {
        if (! $account->hasRole(Role::Vendor->value) || $account->vendor_id === null) {
            throw new InvalidArgumentException('Bukan akun vendor.');
        }
    }
}
