<?php

namespace Database\Factories;

use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vendor>
 */
class VendorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Katering '.fake()->lastName(),
            'contact_person' => fake()->name(),
            'phone' => '628'.fake()->numerify('##########'),
            'address' => fake()->address(),
            'is_active' => true,
        ];
    }
}
