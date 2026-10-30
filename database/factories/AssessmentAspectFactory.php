<?php

namespace Database\Factories;

use App\Models\AssessmentAspect;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssessmentAspect>
 */
class AssessmentAspectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'language_id' => null,
            'code' => null,
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'description' => null,
            'source_aspect' => null,
            'is_default' => false,
            'is_active' => true,
            'sort_order' => 100,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
