<?php

namespace App\Http\Requests;

use App\Enums\LeaveType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeaveRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isParticipant();
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
            'type' => ['required', Rule::enum(LeaveType::class)],
            'date_from' => ['required', 'date', 'after_or_equal:'.now()->subDays(7)->toDateString()],
            'date_to' => ['required', 'date', 'after_or_equal:date_from', 'before_or_equal:'.now()->addDays(60)->toDateString()],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ];
    }
}
