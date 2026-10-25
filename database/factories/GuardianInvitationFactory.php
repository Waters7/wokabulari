<?php

namespace Database\Factories;

use App\Models\GuardianInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GuardianInvitation>
 */
class GuardianInvitationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'participant_id' => User::factory()->participant(),
            'phone' => '6281'.fake()->numerify('#########'),
            'code' => Str::upper(Str::random(8)),
            'expires_at' => now()->addDays(14),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }
}
