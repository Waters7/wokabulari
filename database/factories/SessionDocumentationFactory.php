<?php

namespace Database\Factories;

use App\Enums\DocumentationSource;
use App\Enums\SessionPhase;
use App\Models\LearningSession;
use App\Models\SessionDocumentation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SessionDocumentation>
 */
class SessionDocumentationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'learning_session_id' => LearningSession::factory(),
            'phase' => SessionPhase::Start,
            'source' => DocumentationSource::Camera,
            'path' => 'sesi/dokumentasi/'.fake()->uuid().'.jpg',
            'original_name' => null,
            'mime_type' => 'image/jpeg',
            'size' => 120000,
        ];
    }

    public function end(): static
    {
        return $this->state(fn () => ['phase' => SessionPhase::End]);
    }
}
