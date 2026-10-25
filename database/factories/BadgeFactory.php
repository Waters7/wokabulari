<?php

namespace Database\Factories;

use App\Models\Badge;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Badge>
 */
class BadgeFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Lencana '.fake()->unique()->word();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => 'Lencana contoh',
            'icon' => 'trophy',
            'criteria' => ['type' => 'points', 'min' => 100],
            'is_active' => true,
        ];
    }
}
