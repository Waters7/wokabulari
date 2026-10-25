<?php

namespace Database\Factories;

use App\Models\LearningSession;
use App\Models\SessionNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SessionNote>
 */
class SessionNoteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'learning_session_id' => LearningSession::factory(),
            'user_id' => User::factory()->participant(),
            'teacher_id' => null,
            'note' => 'Pelafalan sudah membaik, tingkatkan kecepatan berbicara.',
            'visible_to_guardian' => false,
        ];
    }
}
