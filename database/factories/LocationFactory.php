<?php

namespace Database\Factories;

use App\Enums\LocationType;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Kampus '.fake()->city(),
            'type' => LocationType::Classroom,
            'latitude' => 1.4748,
            'longitude' => 124.8421,
            'radius_m' => 150,
        ];
    }

    public function dormitory(): static
    {
        return $this->state(fn () => ['name' => 'Asrama '.fake()->lastName(), 'type' => LocationType::Dormitory]);
    }
}
