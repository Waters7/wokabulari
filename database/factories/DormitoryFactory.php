<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Models\Dormitory;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dormitory>
 */
class DormitoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Asrama '.fake()->lastName(),
            'gender' => fake()->randomElement(Gender::cases()),
            'location_id' => Location::factory()->dormitory(),
            'capacity' => 40,
            'curfew_time' => '22:00',
            'check_window_start' => '20:00',
            'check_window_end' => '21:30',
        ];
    }
}
