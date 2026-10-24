<?php

namespace App\Http\Requests;

use App\Models\Language;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LanguageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('batch.manage');
    }

    public function rules(): array
    {
        return self::rulesFor($this->route('language'));
    }

    /**
     * @return array<string, mixed>
     */
    public static function rulesFor(?Language $language = null): array
    {
        return [
            'code' => ['required', 'string', 'alpha_dash', 'max:10', Rule::unique('languages', 'code')->ignore($language?->id)],
            'name' => ['required', 'string', 'max:50'],
            'exam_format' => ['nullable', 'string', 'max:100'],
            'reading_label' => ['nullable', 'string', 'max:50'],
        ];
    }
}
