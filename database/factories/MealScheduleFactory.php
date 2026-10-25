<?php

namespace Database\Factories;

use App\Enums\MealType;
use App\Models\MealSchedule;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MealSchedule>
 */
class MealScheduleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'date' => today(),
            'meal_type' => MealType::Lunch,
            'vendor_id' => Vendor::factory(),
            'menu' => 'Nasi, ikan woku, sayur kangkung, pisang',
            'starts_at' => now()->subMinutes(30),
            'ends_at' => now()->addMinutes(60),
            'portions_ordered' => 30,
        ];
    }

    /** Jadwal yang sudah lewat rentang pengambilannya (hari ini). */
    public function closed(): static
    {
        return $this->state(fn () => ['starts_at' => now()->subHours(3), 'ends_at' => now()->subHours(2)]);
    }

    /** Jadwal yang belum dibuka. */
    public function upcoming(): static
    {
        return $this->state(fn () => ['starts_at' => now()->addHour(), 'ends_at' => now()->addHours(2)]);
    }
}
