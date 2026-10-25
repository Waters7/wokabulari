<?php

namespace Database\Factories;

use App\Enums\PointEvent;
use App\Models\PointTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PointTransaction>
 */
class PointTransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->participant(),
            'event_key' => PointEvent::Manual,
            'points' => 10,
            'description' => 'Poin contoh',
        ];
    }
}
