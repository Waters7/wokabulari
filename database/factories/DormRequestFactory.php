<?php

namespace Database\Factories;

use App\Enums\DormRequestCategory;
use App\Enums\DormRequestStatus;
use App\Enums\Priority;
use App\Models\DormRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DormRequest>
 */
class DormRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->participant(),
            'category' => DormRequestCategory::BrokenFacility,
            'description' => 'Keran kamar mandi bocor sejak kemarin.',
            'priority' => Priority::Normal,
            'status' => DormRequestStatus::New,
        ];
    }
}
