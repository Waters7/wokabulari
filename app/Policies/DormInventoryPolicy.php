<?php

namespace App\Policies;

use App\Models\DormInventory;
use App\Models\User;

class DormInventoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('dorm-inventory.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('dorm-inventory.manage');
    }

    public function update(User $user, DormInventory $item): bool
    {
        return $user->can('dorm-inventory.manage');
    }

    public function delete(User $user, DormInventory $item): bool
    {
        return $user->can('dorm-inventory.manage');
    }
}
