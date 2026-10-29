<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Izin keluar asrama juga bisa disetujui admin program (selain pengelola asrama).
 */
return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::findOrCreate('dorm-exit.approve', 'web');
        Role::query()->where('name', 'program_admin')->where('guard_name', 'web')->first()?->givePermissionTo($permission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::query()->where('name', 'program_admin')->where('guard_name', 'web')->first()?->revokePermissionTo('dorm-exit.approve');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};