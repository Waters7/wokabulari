<?php

namespace Database\Factories;

use App\Actions\Ratings\SubmitRating;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorRating;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendorRating>
 */
class VendorRatingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'vendor_id' => Vendor::factory(),
            'user_id' => User::factory()->participant(),
            'week_start' => SubmitRating::weekStart(),
            'rating' => fake()->numberBetween(3, 5),
            'taste' => fake()->numberBetween(2, 5),
            'portion' => fake()->numberBetween(2, 5),
            'cleanliness' => fake()->numberBetween(3, 5),
            'punctuality' => fake()->numberBetween(3, 5),
            'comment' => fake()->randomElement([null, 'Enak, porsi pas.', 'Sayurnya kurang segar.', 'Sambal roa mantap!']),
            'suggestion' => null,
        ];
    }
}
