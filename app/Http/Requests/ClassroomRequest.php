<?php

namespace App\Http\Requests;

use App\Models\Classroom;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClassroomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('classroom.manage');
    }

    public function rules(): array
    {
        return self::rulesFor($this->route('classroom'));
    }

    /**
     * @return array<string, mixed>
     */
    public static function rulesFor(?Classroom $classroom = null): array
    {
        return [
            'batch_id' => ['required', 'exists:batches,id'],
            'language_id' => ['required', 'exists:languages,id'],
            'name' => ['required', 'string', 'max:100'],
            'level' => ['nullable', 'string', 'max:50'],
            'teacher_id' => ['nullable', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'location_id' => ['nullable', 'exists:locations,id'],
        ];
    }
}
