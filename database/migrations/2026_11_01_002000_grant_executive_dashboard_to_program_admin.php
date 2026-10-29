<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Dashboard eksekutif juga dibuka untuk admin program (baca saja), selain pimpinan & super admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::findOrCreate('dashboard.leader', 'web');
        Role::query()->where('name', 'program_admin')->where('guard_name', 'web')->first()?->givePermissionTo($permission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::query()->where('name', 'program_admin')->where('guard_name', 'web')->first()?->revokePermissionTo('dashboard.leader');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
