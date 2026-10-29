<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VocabularyWordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('word.manage');
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
            'language_id' => ['required', 'integer', Rule::exists('languages', 'id')],
            'word' => ['required', 'string', 'max:100'],
            'reading' => ['nullable', 'string', 'max:150'],
            'meaning' => ['required', 'string', 'max:255'],
            'example' => ['nullable', 'string', 'max:500'],
            'example_meaning' => ['nullable', 'string', 'max:500'],
            'topic' => ['nullable', 'string', 'max:60'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributesFor(): array
    {
        return [
            'language_id' => 'bahasa', 'word' => 'kata', 'reading' => 'cara baca', 'meaning' => 'arti',
            'example' => 'contoh kalimat', 'example_meaning' => 'arti contoh', 'topic' => 'topik', 'sort_order' => 'urutan',
        ];
    }
}
