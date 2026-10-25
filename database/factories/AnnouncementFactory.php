<?php

namespace Database\Factories;

use App\Enums\AnnouncementAudience;
use App\Enums\AnnouncementSource;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => 'Pengumuman '.fake()->words(3, true),
            'body' => fake()->paragraph(),
            'source' => AnnouncementSource::Team,
            'audience' => AnnouncementAudience::All,
            'published_at' => now()->subMinute(),
            'notified_at' => now(),
            'created_by' => User::factory()->programAdmin(),
        ];
    }
}
