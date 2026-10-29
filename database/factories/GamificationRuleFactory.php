<?php

namespace Database\Factories;

use App\Enums\PointCategory;
use App\Enums\PointEvent;
use App\Enums\ReviewStatus;
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
            'category' => PointCategory::Attendance,
            'is_active' => true,
        ];
    }

    /** Komponen tambahan yang sudah disetujui (diberikan manual). */
    public function customComponent(int $points = 10): static
    {
        return $this->state(fn () => [
            'event_key' => null,
            'name' => 'Presentasi terbaik',
            'category' => PointCategory::Engagement,
            'points' => $points,
            'is_custom' => true,
            'status' => ReviewStatus::Approved,
        ]);
    }
}
