<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Enums\StaffUnit;
use App\Models\ParticipantProfile;
use App\Models\User;
use App\Services\StaffUnitService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role as RoleModel;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'username' => null,
            'phone' => '628'.fake()->numerify('##########'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function consented(): static
    {
        return $this->state(fn () => [
            'consented_at' => now(),
            'consent_version' => config('wokabulari.consent_version'),
        ]);
    }

    public function withRole(Role $role): static
    {
        return $this->afterCreating(function (User $user) use ($role) {
            RoleModel::findOrCreate($role->value, 'web');
            $user->assignRole($role->value);
        });
    }

    public function superAdmin(): static
    {
        return $this->withRole(Role::SuperAdmin);
    }

    public function leader(): static
    {
        return $this->withRole(Role::Leader);
    }

    public function programAdmin(): static
    {
        return $this->withRole(Role::ProgramAdmin);
    }

    public function teacher(): static
    {
        return $this->state(fn () => ['username' => fake()->unique()->numerify('19##############')])
            ->withRole(Role::Teacher);
    }

    public function participant(): static
    {
        return $this->state(fn () => ['username' => fake()->unique()->numerify('NIS#####')])
            ->consented()
            ->withRole(Role::Participant)
            ->afterCreating(fn (User $user) => ParticipantProfile::factory()->for($user)->create());
    }

    public function guardian(): static
    {
        return $this->consented()->withRole(Role::Guardian);
    }

    /**
     * @param  list<StaffUnit>  $units
     */
    public function staff(array $units = [StaffUnit::Consumption]): static
    {
        return $this->withRole(Role::Staff)
            ->afterCreating(fn (User $user) => app(StaffUnitService::class)->sync($user, $units));
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }
}
