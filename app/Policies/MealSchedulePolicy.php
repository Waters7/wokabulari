<?php

namespace App\Policies;

use App\Models\MealSchedule;
use App\Models\User;

class MealSchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('meal.manage') || $user->can('meal.monitor') || $user->receivesMeals();
    }

    public function view(User $user, MealSchedule $schedule): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('meal.manage');
    }

    public function update(User $user, MealSchedule $schedule): bool
    {
        return $user->can('meal.manage');
    }

    public function delete(User $user, MealSchedule $schedule): bool
    {
        return $user->can('meal.manage') && ! $schedule->receipts()->exists();
    }

    /** Peserta (dan tim yang ditandai menerima konsumsi) menandatangani penerimaannya sendiri. */
    public function receive(User $user, ?MealSchedule $schedule = null): bool
    {
        return $user->receivesMeals();
    }

    /** Petugas konsumsi mencatat penerimaan diwakilkan. */
    public function proxy(User $user, ?MealSchedule $schedule = null): bool
    {
        return $user->can('meal.proxy');
    }
}
