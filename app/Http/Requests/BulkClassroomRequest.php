<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkClassroomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('participant.manage');
    }

    public function rules(): array
    {
        return self::rulesFor();
    }

    /**
     * Atur kelas banyak peserta: satu kelas, atau bagi rata ke beberapa kelas.
     *
     * @return array<string, mixed>
     */
    public static function rulesFor(): array
    {
        return [
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'mode' => ['required', Rule::in(['single', 'distribute'])],
            'classroom_id' => ['required_if:mode,single', 'nullable', 'exists:classrooms,id'],
            'classroom_ids' => ['required_if:mode,distribute', 'array'],
            'classroom_ids.*' => ['integer', 'exists:classrooms,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messagesFor(): array
    {
        return [
            'user_ids.required' => 'Pilih peserta dulu.',
            'classroom_id.required_if' => 'Pilih kelas tujuan.',
            'classroom_ids.required_if' => 'Pilih minimal satu kelas.',
        ];
    }
}
