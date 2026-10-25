<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role as RoleModel;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('app:create-admin {--name=} {--username= : Username login (mis. admin)} {--email=} {--password=}')]
#[Description('Buat akun super admin (untuk produksi, tanpa data contoh). Login memakai username atau email.')]
class CreateAdminCommand extends Command
{
    public function handle(): int
    {
        if (! RoleModel::query()->where('name', Role::SuperAdmin->value)->exists()) {
            $this->call('db:seed', ['--class' => RolePermissionSeeder::class, '--force' => true]);
        }

        $name = $this->option('name') ?: text('Nama lengkap', default: 'Super Admin', required: true);
        $username = $this->option('username') ?? text('Username login (kosongkan bila memakai email)');
        $email = $this->option('email') ?? text('Email (opsional bila username diisi)');
        $password = $this->option('password') ?: password('Kata sandi (min. 8 karakter)', required: true);

        $data = ['name' => $name, 'username' => $username ?: null, 'email' => $email ? strtolower($email) : null, 'password' => $password];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:150'],
            'username' => ['nullable', 'required_without:email', 'string', 'max:50', Rule::unique('users', 'username')],
            'email' => ['nullable', 'required_without:username', 'email', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8'],
        ], [], ['username' => 'username', 'email' => 'email', 'password' => 'kata sandi']);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::query()->create($data + ['email_verified_at' => now(), 'must_change_password' => false]);
        $user->assignRole(Role::SuperAdmin->value);

        $this->info('Super admin '.($user->username ?? $user->email).' berhasil dibuat.');

        return self::SUCCESS;
    }
}
