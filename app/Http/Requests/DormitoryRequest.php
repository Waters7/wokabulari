<?php

namespace App\Http\Requests;

use App\Enums\Gender;
use App\Models\Dormitory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DormitoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('dormitory.manage');
    }

    public function rules(): array
    {
        return self::rulesFor($this->route('dormitory'));
    }

    /**
     * @return array<string, mixed>
     */
    public static function rulesFor(?Dormitory $dormitory = null): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'location_id' => ['nullable', 'exists:locations,id'],
            'capacity' => ['required', 'integer', 'min:0', 'max:2000'],
            'curfew_time' => ['required', 'date_format:H:i'],
            'check_window_start' => ['required', 'date_format:H:i'],
            'check_window_end' => ['required', 'date_format:H:i', 'after:check_window_start'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function roomRules(int $dormitoryId, ?int $roomId = null): array
    {
        return [
            'number' => ['required', 'string', 'max:20', Rule::unique('dorm_rooms', 'number')->where('dormitory_id', $dormitoryId)->ignore($roomId)->whereNull('deleted_at')],
            'floor' => ['nullable', 'string', 'max:10'],
            'capacity' => ['required', 'integer', 'between:1,20'],
        ];
    }
}
