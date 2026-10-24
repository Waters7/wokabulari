<?php

namespace App\Services;

use App\Enums\StaffUnit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Menyimpan unit tim kerja dan menyelaraskan permission langsung sesuai unit.
 */
class StaffUnitService
{
    /**
     * @param  list<StaffUnit|string>  $units
     */
    public function sync(User $user, array $units): void
    {
        $units = collect($units)
            ->map(fn (StaffUnit|string $unit) => $unit instanceof StaffUnit ? $unit : StaffUnit::from($unit))
            ->unique()
            ->values();

        DB::transaction(function () use ($user, $units) {
            $user->staffUnits()->whereNotIn('unit', $units->map->value)->delete();

            foreach ($units as $unit) {
                $user->staffUnits()->firstOrCreate(['unit' => $unit->value]);
            }

            $permissions = $units
                ->flatMap(fn (StaffUnit $unit) => config("wokabulari.unit_permissions.{$unit->value}", []))
                ->unique()
                ->values()
                ->all();

            $user->syncPermissions($permissions);
        });

        $user->unsetRelation('staffUnits');
    }
}
