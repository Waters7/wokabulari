<?php

namespace App\Policies;

class DormitoryPolicy extends PermissionPolicy
{
    protected string $manage = 'dormitory.manage';

    protected ?string $view = 'attendance.confirm-dorm';
}
