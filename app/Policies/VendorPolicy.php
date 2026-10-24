<?php

namespace App\Policies;

class VendorPolicy extends PermissionPolicy
{
    protected string $manage = 'vendor.manage';

    protected ?string $view = 'meal.monitor';
}
