<?php

namespace Database\Factories;

use App\Enums\ExitPermitStatus;
use App\Enums\ExitPermitType;
use App\Models\DormExitPermit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DormExitPermit>
 */
class DormExitPermitFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->participant(),
            'type' => ExitPermitType::Temporary,
            'reason' => 'Membeli perlengkapan mandi',
            'destination' => 'Mega Mall Manado',
            'companion' => 'Teman sekamar',
            'contact_phone' => '081234567890',
            'planned_departure' => now()->addHours(4),
            'planned_return' => now()->addHours(7),
            'status' => ExitPermitStatus::Submitted,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => ExitPermitStatus::Approved, 'decided_at' => now(), 'qr_token' => Str::random(40)]);
    }

    public function out(): static
    {
        return $this->state(fn () => [
            'status' => ExitPermitStatus::Out,
            'planned_departure' => now()->subHours(3),
            'planned_return' => now()->subMinutes(10),
            'actual_departure' => now()->subHours(3),
            'qr_token' => Str::random(40),
        ]);
    }
}
