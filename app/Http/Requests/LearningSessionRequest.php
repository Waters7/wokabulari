<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LearningSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('session.manage');
    }

    public function rules(): array
    {
        return self::rulesFor();
    }

    /**
     * @return array<string, mixed>
     */
    public static function rulesFor(bool $creating = true): array
    {
        return [
            'classroom_id' => ['required', 'exists:classrooms,id'],
            'date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'topic' => ['required', 'string', 'max:200'],
            'objectives' => ['nullable', 'string', 'max:2000'],
            'repeat' => $creating ? ['boolean'] : ['prohibited'],
            'weekdays' => ['array', 'required_if_accepted:repeat'],
            'weekdays.*' => ['integer', 'between:1,7'],
            'weeks' => ['nullable', 'integer', 'between:1,26', 'required_if_accepted:repeat'],
        ];
    }
}
