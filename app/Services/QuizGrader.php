<?php

namespace App\Services;

use App\Enums\QuestionType;
use App\Models\Question;
use Normalizer;

/**
 * Penilaian otomatis per soal. Esai & berbicara menunggu penilaian pengajar (score null).
 */
class QuizGrader
{
    /**
     * @param  array<string, mixed>|null  $answer
     * @return array{is_correct: bool|null, score: float|null}
     */
    public function grade(Question $question, ?array $answer, float $points, bool $hasAudio = false): array
    {
        $type = $question->type;

        if (! $type->isAutoGraded()) {
            $answered = $type === QuestionType::Speaking ? $hasAudio : filled($answer['text'] ?? null);

            // Tidak dijawab = 0; dijawab = menunggu pengajar.
            return $answered ? ['is_correct' => null, 'score' => null] : ['is_correct' => false, 'score' => 0.0];
        }

        if ($type->usesChoice()) {
            $correct = $question->options->firstWhere('is_correct', true)?->id;
            $chosen = (int) ($answer['option'] ?? 0);
            $ok = $correct !== null && $chosen === $correct;

            return ['is_correct' => $ok, 'score' => $ok ? $points : 0.0];
        }

        if ($type === QuestionType::ShortAnswer) {
            $given = self::normalize($answer['text'] ?? '');
            $ok = $given !== '' && $question->options->contains(fn ($o) => self::normalize($o->body) === $given);

            return ['is_correct' => $ok, 'score' => $ok ? $points : 0.0];
        }

        // Menjodohkan: nilai parsial per pasangan benar.
        $pairs = $answer['pairs'] ?? [];
        $total = $question->options->count();
        $right = $question->options->filter(fn ($o) => self::normalize($pairs[$o->id] ?? '') === self::normalize($o->match_key))->count();

        return [
            'is_correct' => $total > 0 && $right === $total,
            'score' => $total > 0 ? round($points * $right / $total, 2) : 0.0,
        ];
    }

    /**
     * Normalisasi jawaban: huruf kecil, spasi rapi, tanda baca akhir dibuang (termasuk tanda baca CJK).
     */
    public static function normalize(?string $value): string
    {
        $value = (string) $value;

        if (class_exists(Normalizer::class)) {
            $value = Normalizer::normalize($value, Normalizer::FORM_KC) ?: $value;
        }

        $value = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value)));

        return preg_replace('/[\s.,!?;:。，！？；：、]+$/u', '', $value);
    }
}
