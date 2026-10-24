<?php

namespace Database\Factories;

use App\Models\Batch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Batch>
 */
class BatchFactory extends Factory
{
    public function definition(): array
    {
        $start = now()->startOfWeek()->subWeek();

        return [
            'name' => 'Angkatan '.fake()->numberBetween(1, 9).' '.now()->year,
            'start_date' => $start,
            'end_date' => $start->copy()->addWeeks(12),
            'attendance_target' => 80,
            'passing_score' => 70,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
