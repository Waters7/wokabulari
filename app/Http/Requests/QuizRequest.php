<?php

namespace App\Http\Requests;

use App\Enums\LanguageAspect;
use App\Enums\QuestionType;
use App\Enums\QuizType;
use App\Enums\ReadingDisplay;
use App\Enums\ResultVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuizRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('quiz.manage');
    }

    public function rules(): array
    {
        return self::rulesFor();
    }

    /**
     * @return array<string, mixed>
     */
    public static function rulesFor(): array
    {
        return [
            'classroom_id' => ['required', 'exists:classrooms,id'],
            'learning_session_id' => ['nullable', 'exists:learning_sessions,id'],
            'type' => ['required', Rule::enum(QuizType::class)],
            'title' => ['required', 'string', 'max:200'],
            'instructions' => ['nullable', 'string', 'max:3000'],
            'aspect' => ['required', Rule::enum(LanguageAspect::class)],
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date', 'after:opens_at'],
            'duration_minutes' => ['required', 'integer', 'between:1,300'],
            'max_attempts' => ['required', 'integer', 'between:1,10'],
            'passing_score' => ['required', 'numeric', 'between:0,100'],
            'shuffle_questions' => ['boolean'],
            'shuffle_options' => ['boolean'],
            'result_visibility' => ['required', Rule::enum(ResultVisibility::class)],
            'reading_display' => ['required', Rule::enum(ReadingDisplay::class)],
            'score_scale' => ['required', 'integer', 'between:10,1000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function bankRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'language_id' => ['required', 'exists:languages,id'],
            'level' => ['nullable', 'string', 'max:50'],
            'topic' => ['nullable', 'string', 'max:150'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function questionRules(): array
    {
        return [
            'type' => ['required', Rule::enum(QuestionType::class)],
            'body' => ['required', 'string', 'max:5000'],
            'reading' => ['nullable', 'string', 'max:2000'],
            'points' => ['required', 'numeric', 'min:0.5', 'max:100'],
            'explanation' => ['nullable', 'string', 'max:3000'],
            'options' => ['array', 'max:12'],
            'options.*.body' => ['nullable', 'string', 'max:1000'],
            'options.*.reading' => ['nullable', 'string', 'max:255'],
            'options.*.match_key' => ['nullable', 'string', 'max:255'],
            'accepted_answers' => ['array', 'max:10'],
            'accepted_answers.*' => ['nullable', 'string', 'max:255'],
            'correct_bool' => ['boolean'],
            'correct_tone' => ['nullable', 'integer', 'between:1,4'],
            'media' => ['nullable', 'file', 'mimetypes:audio/mpeg,audio/mp4,audio/x-m4a,audio/wav,audio/x-wav,audio/ogg,audio/webm,image/jpeg,image/png,image/webp', 'max:20480'],
        ];
    }
}
