<?php

namespace Database\Factories;

use App\Enums\SessionStatus;
use App\Models\Classroom;
use App\Models\LearningSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningSession>
 */
class LearningSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'classroom_id' => Classroom::factory(),
            'teacher_id' => fn (array $attributes) => Classroom::find($attributes['classroom_id'])?->teacher_id,
            'date' => today(),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'topic' => fake()->randomElement(['Perkenalan Diri', 'Angka & Waktu', 'Di Rumah Sakit', 'Percakapan Telepon', 'Membaca Resep Obat']),
            'objectives' => 'Peserta mampu memperkenalkan diri dalam situasi kerja.',
            'status' => SessionStatus::Scheduled,
        ];
    }

    public function ongoing(): static
    {
        return $this->state(fn () => ['status' => SessionStatus::Ongoing, 'started_at' => now()]);
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => SessionStatus::Completed, 'started_at' => now()->subHours(2), 'ended_at' => now()]);
    }
}
