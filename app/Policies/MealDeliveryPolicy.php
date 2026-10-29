<?php

namespace App\Policies;

use App\Models\MealDelivery;
use App\Models\User;

class MealDeliveryPolicy
{
    /** Vendor melihat laporannya sendiri; tim konsumsi melihat semua. */
    public function viewAny(User $user): bool
    {
        return $user->can('meal.deliver') || $user->can('meal.monitor') || $user->can('meal.manage');
    }

    public function view(User $user, MealDelivery $delivery): bool
    {
        return ($user->can('meal.deliver') && $user->vendor_id === $delivery->vendor_id)
            || $user->can('meal.monitor') || $user->can('meal.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('meal.deliver') && $user->isVendor();
    }

    /** Hanya vendor pemilik laporan, dan hanya untuk hari ini atau setelahnya. */
    public function update(User $user, MealDelivery $delivery): bool
    {
        return $user->can('meal.deliver')
            && $user->vendor_id === $delivery->vendor_id
            && ! $delivery->schedule->date->lt(today());
    }
}
