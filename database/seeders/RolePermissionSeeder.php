<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use App\Services\StaffUnitService;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (config('wokabulari.permissions') as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (Role::cases() as $role) {
            $model = RoleModel::findOrCreate($role->value, 'web');

            $permissions = $role === Role::SuperAdmin
                ? config('wokabulari.permissions')
                : config("wokabulari.role_permissions.{$role->value}", []);

            $model->syncPermissions($permissions);
        }

        // Selaraskan ulang permission langsung tim kerja bila daftar permission unit berubah.
        User::query()->whereHas('staffUnits')->with('staffUnits')->each(function (User $user) {
            app(StaffUnitService::class)->sync($user, $user->staffUnits->pluck('unit')->all());
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
