<?php

namespace Database\Factories;

use App\Models\Classroom;
use App\Models\GamificationRule;
use App\Models\PointAward;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PointAward>
 */
class PointAwardFactory extends Factory
{
    public function definition(): array
    {
        return [
            'gamification_rule_id' => GamificationRule::factory()->customComponent(),
            'classroom_id' => Classroom::factory(),
            'points' => 10,
            'awarded_by' => User::factory()->teacher(),
        ];
    }
}
