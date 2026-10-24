<?php

namespace Database\Factories;

use App\Enums\MaterialType;
use App\Enums\MaterialVisibility;
use App\Models\LearningSession;
use App\Models\SessionMaterial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SessionMaterial>
 */
class SessionMaterialFactory extends Factory
{
    public function definition(): array
    {
        return [
            'learning_session_id' => LearningSession::factory(),
            'teacher_id' => fn (array $a) => LearningSession::find($a['learning_session_id'])?->teacher_id,
            'title' => 'Materi '.fake()->words(3, true),
            'description' => fake()->sentence(),
            'type' => MaterialType::Link,
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'visibility' => MaterialVisibility::Immediate,
            'is_downloadable' => true,
        ];
    }

    public function file(string $path = 'materials/contoh.pdf', string $mime = 'application/pdf'): static
    {
        return $this->state(fn () => ['type' => MaterialType::File, 'url' => null, 'file_path' => $path, 'mime' => $mime, 'original_name' => basename($path), 'size' => 2048]);
    }

    public function afterSession(): static
    {
        return $this->state(fn () => ['visibility' => MaterialVisibility::AfterSession]);
    }
}
