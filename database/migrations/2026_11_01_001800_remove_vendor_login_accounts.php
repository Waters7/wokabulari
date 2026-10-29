<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Input pengantaran makanan kini dilakukan tim konsumsi, bukan vendor.
 * Akun login vendor dinonaktifkan (tidak dihapus) dan role/izin vendor dicabut.
 */
return new class extends Migration
{
    public function up(): void
    {
        $role = Role::query()->where('name', 'vendor')->where('guard_name', 'web')->first();

        if ($role) {
            $userIds = DB::table('model_has_roles')->where('role_id', $role->id)
                ->where('model_type', (new User)->getMorphClass())->pluck('model_id');
            DB::table('users')->whereIn('id', $userIds)->update(['is_active' => false]);
            $role->delete();
        }

        Permission::query()->where('name', 'meal.deliver')->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permission = Permission::findOrCreate('meal.deliver', 'web');
        Role::findOrCreate('vendor', 'web')->givePermissionTo($permission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
