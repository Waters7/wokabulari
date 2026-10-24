<?php

namespace App\Policies;

class LocationPolicy extends PermissionPolicy
{
    protected string $manage = 'location.manage';
}
