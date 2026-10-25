<?php

namespace Database\Factories;

use App\Actions\Ratings\SubmitRating;
use App\Models\Classroom;
use App\Models\TeacherRating;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeacherRating>
 */
class TeacherRatingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'teacher_id' => User::factory()->teacher(),
            'user_id' => User::factory()->participant(),
            'classroom_id' => Classroom::factory(),
            'week_start' => SubmitRating::weekStart(),
            'rating' => fake()->numberBetween(3, 5),
            'mastery' => fake()->numberBetween(3, 5),
            'delivery' => fake()->numberBetween(3, 5),
            'discipline' => fake()->numberBetween(3, 5),
            'interaction' => fake()->numberBetween(3, 5),
            'comment' => fake()->randomElement([null, 'Penjelasannya jelas.', 'Kalau bisa lebih banyak latihan percakapan.']),
            'suggestion' => null,
            'is_anonymous' => false,
        ];
    }

    public function anonymous(): static
    {
        return $this->state(fn () => ['is_anonymous' => true]);
    }
}
