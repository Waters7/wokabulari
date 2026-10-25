<?php

namespace App\Http\Requests;

use App\Enums\MealType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MealScheduleRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public static function rulesFor(): array
    {
        return [
            'date' => ['required', 'date'],
            'meal_type' => ['required', Rule::enum(MealType::class)],
            'vendor_id' => ['required', Rule::exists('vendors', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'menu' => ['required', 'string', 'max:500'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'portions_ordered' => ['required', 'integer', 'min:0', 'max:5000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function ratingRules(array $aspects, bool $teacher = false): array
    {
        $rules = [
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'suggestion' => ['nullable', 'string', 'max:2000'],
        ];

        foreach ($aspects as $aspect) {
            $rules[$aspect] = ['nullable', 'integer', 'between:1,5'];
        }

        if ($teacher) {
            $rules['is_anonymous'] = ['boolean'];
        }

        return $rules;
    }
}
