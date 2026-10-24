<?php

namespace App\Policies;

class LanguagePolicy extends PermissionPolicy
{
    protected string $manage = 'batch.manage';
}
