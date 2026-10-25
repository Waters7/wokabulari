<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use App\Models\ParticipantProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ParticipantProfile>
 */
class ParticipantProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'school_name' => 'SMK '.fake()->randomElement(['Negeri 1 Manado', 'Kristen Tomohon', 'Negeri 2 Bitung', 'Yadika Manado']),
            'major' => fake()->randomElement(['Keperawatan', 'Farmasi', 'Analis Kesehatan', 'Perhotelan']),
            'gender' => fake()->randomElement(Gender::cases()),
            'birth_date' => fake()->dateTimeBetween('-19 years', '-16 years'),
            'allergies' => null,
            'medical_history' => null,
            'emergency_contact_name' => fake()->name(),
            'emergency_contact_phone' => '628'.fake()->numerify('##########'),
            'guardian_name' => fake()->name(),
            'guardian_phone' => '628'.fake()->numerify('##########'),
            'guardian_relationship' => GuardianRelationship::Mother,
            'target_exam' => null,
            'target_score' => null,
        ];
    }

    /** Peserta yang belum mengisi kontak orang tua/wali. */
    public function withoutGuardianContact(): static
    {
        return $this->state(fn () => ['guardian_name' => null, 'guardian_phone' => null, 'guardian_relationship' => null]);
    }

    /**
     * Descriptor wajah palsu (128 dimensi) untuk pengujian.
     *
     * @param  list<float>|null  $descriptor
     */
    public function withFace(?array $descriptor = null): static
    {
        return $this->state(fn () => [
            'face_descriptor' => $descriptor ?? array_fill(0, 128, 0.1),
            'face_enrolled_at' => now(),
        ]);
    }
}
