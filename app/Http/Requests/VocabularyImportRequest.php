<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VocabularyImportRequest extends FormRequest
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
     * Berkas import Bank Kosakata (templat Excel atau CSV).
     *
     * @return array<string, mixed>
     */
    public static function rulesFor(): array
    {
        return [
            'language_id' => ['required', 'integer', Rule::exists('languages', 'id')],
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributesFor(): array
    {
        return ['language_id' => 'bahasa', 'file' => 'berkas'];
    }
}
