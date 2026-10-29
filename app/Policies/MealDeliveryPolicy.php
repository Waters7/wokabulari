<?php

namespace App\Policies;

use App\Actions\Meals\ReportMealDelivery;
use App\Models\MealDelivery;
use App\Models\User;

class MealDeliveryPolicy
{
    /** Tim konsumsi menginput; monitor & laporan bisa melihat. */
    public function viewAny(User $user): bool
    {
        return $user->can('meal.monitor') || $user->can('meal.manage');
    }

    public function view(User $user, MealDelivery $delivery): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('meal.manage');
    }

    /** Input susulan/perbaikan sampai beberapa hari ke belakang. */
    public function update(User $user, MealDelivery $delivery): bool
    {
        return $user->can('meal.manage')
            && ! $delivery->schedule->date->lt(today()->subDays(ReportMealDelivery::BACKDATE_DAYS));
    }
}
