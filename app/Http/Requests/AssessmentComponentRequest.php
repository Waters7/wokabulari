<?php

namespace App\Http\Requests;

use App\Enums\LanguageAspect;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssessmentComponentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assessment.manage');
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
            'name' => ['required', 'string', 'max:100'],
            'aspect' => ['required', Rule::enum(LanguageAspect::class)],
            'weight' => ['required', 'numeric', 'min:1', 'max:100'],
            'max_score' => ['required', 'numeric', 'min:1', 'max:1000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function noteRules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
            'aspect' => ['nullable', Rule::enum(LanguageAspect::class)],
            'note' => ['required', 'string', 'min:3', 'max:2000'],
            'visible_to_guardian' => ['boolean'],
        ];
    }
}
