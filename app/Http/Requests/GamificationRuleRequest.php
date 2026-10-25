<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GamificationRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('gamification.manage');
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
            'points' => ['required', 'integer', 'between:-100,200'],
            'max_per_day' => ['nullable', 'integer', 'between:1,50'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Penyesuaian poin manual untuk satu peserta.
     *
     * @return array<string, mixed>
     */
    public static function adjustmentRules(): array
    {
        return [
            'user_id' => ['required', Rule::exists('users', 'id')],
            'points' => ['required', 'integer', 'between:-500,500', 'not_in:0'],
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function levelRules(): array
    {
        return [
            'levels' => ['array'],
            'levels.*.name' => ['required', 'string', 'max:40'],
            'levels.*.min_points' => ['required', 'integer', 'min:0', 'max:100000', 'distinct'],
        ];
    }
}
