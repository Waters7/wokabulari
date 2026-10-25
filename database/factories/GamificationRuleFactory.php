<?php

namespace Database\Factories;

use App\Enums\PointEvent;
use App\Models\GamificationRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GamificationRule>
 */
class GamificationRuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_key' => PointEvent::DailyAttendanceOnTime,
            'name' => PointEvent::DailyAttendanceOnTime->label(),
            'points' => 10,
            'is_active' => true,
        ];
    }
}
