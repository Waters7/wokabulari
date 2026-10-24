<?php

use App\Enums\StaffUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Helper pembuat pengguna per role
|--------------------------------------------------------------------------
*/

function superAdmin(): User
{
    return User::factory()->superAdmin()->create();
}

function leader(): User
{
    return User::factory()->leader()->create();
}

function programAdmin(): User
{
    return User::factory()->programAdmin()->create();
}

function teacher(): User
{
    return User::factory()->teacher()->create();
}

function participant(): User
{
    return User::factory()->participant()->create();
}

function guardian(): User
{
    return User::factory()->guardian()->create();
}

/**
 * @param  list<StaffUnit>  $units
 */
function staff(array $units = [StaffUnit::Consumption]): User
{
    return User::factory()->staff($units)->create();
}
