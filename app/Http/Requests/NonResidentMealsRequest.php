<?php

namespace App\Http\Requests;

use App\Enums\MealType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NonResidentMealsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('meal.manage');
    }

    public function rules(): array
    {
        return self::rulesFor();
    }

    /**
     * Jenis konsumsi yang diterima peserta tidak asrama (boleh kosong).
     *
     * @return array<string, mixed>
     */
    public static function rulesFor(): array
    {
        return [
            'types' => ['present', 'array'],
            'types.*' => ['distinct', Rule::enum(MealType::class)],
        ];
    }
}
