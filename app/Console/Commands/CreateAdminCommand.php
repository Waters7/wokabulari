<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role as RoleModel;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('app:create-admin {--name=} {--email=} {--password=}')]
#[Description('Buat akun super admin (untuk produksi, tanpa data contoh).')]
class CreateAdminCommand extends Command
{
    public function handle(): int
    {
        if (! RoleModel::query()->where('name', Role::SuperAdmin->value)->exists()) {
            $this->call('db:seed', ['--class' => RolePermissionSeeder::class, '--force' => true]);
        }

        $name = $this->option('name') ?: text('Nama lengkap', required: true);
        $email = $this->option('email') ?: text('Email', required: true);
        $password = $this->option('password') ?: password('Kata sandi (min. 8 karakter)', required: true);

        $validator = Validator::make(compact('name', 'email', 'password'), [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::query()->create([
            'name' => $name,
            'email' => strtolower($email),
            'password' => $password,
            'email_verified_at' => now(),
        ]);
        $user->assignRole(Role::SuperAdmin->value);

        $this->info("Super admin {$user->email} berhasil dibuat.");

        return self::SUCCESS;
    }
}
