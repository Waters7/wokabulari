<?php

namespace App\Policies;

class DormRoomPolicy extends PermissionPolicy
{
    protected string $manage = 'dormitory.manage';

    protected ?string $view = 'attendance.confirm-dorm';
}
