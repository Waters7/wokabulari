<?php

namespace App\Http\Requests;

use App\Enums\LanguageAspect;
use App\Models\AssessmentAspect;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssessmentAspectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assessment-aspect.manage');
    }

    public function rules(): array
    {
        return self::rulesFor($this->route('aspect'), $this->input('language_id'));
    }

    /**
     * @return array<string, mixed>
     */
    public static function rulesFor(?AssessmentAspect $aspect = null, mixed $languageId = null): array
    {
        return [
            'name' => ['required', 'string', 'max:60', Rule::unique('assessment_aspects', 'name')
                ->where(fn ($q) => filled($languageId) ? $q->where('language_id', $languageId) : $q->whereNull('language_id'))
                ->ignore($aspect?->id)],
            'language_id' => ['nullable', 'integer', Rule::exists('languages', 'id')],
            'description' => ['nullable', 'string', 'max:200'],
            'source_aspect' => ['nullable', Rule::enum(LanguageAspect::class)],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
