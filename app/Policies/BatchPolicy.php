<?php

namespace App\Policies;

class BatchPolicy extends PermissionPolicy
{
    protected string $manage = 'batch.manage';
}
