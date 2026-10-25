<?php

namespace App\Http\Requests;

use App\Enums\HealthActionType;
use App\Enums\HealthSeverity;
use App\Models\HealthReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HealthReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', HealthReport::class);
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
            'symptoms' => ['required', 'array', 'min:1'],
            'symptoms.*' => [Rule::in(array_keys(HealthReport::SYMPTOMS))],
            'description' => ['nullable', 'string', 'max:2000'],
            'started_at' => ['nullable', 'date', 'before_or_equal:now'],
            'temperature' => ['nullable', 'numeric', 'between:34,43'],
            'severity' => ['required', Rule::enum(HealthSeverity::class)],
            'needs_special_meal' => ['boolean'],
            'special_meal_note' => ['nullable', 'string', 'max:255'],
            'deliver_to_room' => ['boolean'],
            'photo' => ['nullable', 'image', 'max:8192'],
        ];
    }

    /**
     * Tindakan penanganan oleh petugas.
     *
     * @return array<string, mixed>
     */
    public static function actionRules(): array
    {
        return [
            'action_type' => ['required', Rule::enum(HealthActionType::class)],
            'medicine' => ['nullable', 'string', 'max:120'],
            'dosage' => ['nullable', 'string', 'max:120'],
            'referral_place' => ['nullable', 'string', 'max:255'],
            'condition_note' => ['nullable', 'string', 'max:2000'],
            'next_check_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
