<?php

namespace Database\Factories;

use App\Models\Batch;
use App\Models\Classroom;
use App\Models\Language;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Classroom>
 */
class ClassroomFactory extends Factory
{
    public function definition(): array
    {
        return [
            'batch_id' => Batch::factory(),
            'language_id' => Language::factory(),
            'name' => 'Kelas '.fake()->randomElement(['A', 'B', 'C']),
            'level' => fake()->randomElement(['Dasar', 'Menengah']),
            'teacher_id' => User::factory()->teacher(),
            'location_id' => Location::factory(),
        ];
    }

    /**
     * Tambahkan peserta ke kelas.
     */
    public function withParticipants(int $count = 3): static
    {
        return $this->afterCreating(function (Classroom $classroom) use ($count) {
            $participants = User::factory()->count($count)->participant()->create();
            $classroom->participants()->attach($participants->pluck('id'), ['joined_at' => now()]);
        });
    }
}
