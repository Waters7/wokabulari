<?php

namespace App\Http\Requests;

use App\Enums\Gender;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ParticipantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('participant.manage');
    }

    public function rules(): array
    {
        return self::rulesFor($this->route('user'));
    }

    /**
     * @return array<string, mixed>
     */
    public static function rulesFor(?User $user = null): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'username' => ['nullable', 'string', 'max:50', Rule::unique('users', 'username')->ignore($user?->id)],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($user?->id)->whereNull('deleted_at')],
            'is_active' => ['boolean'],
            'classroom_id' => ['nullable', 'exists:classrooms,id'],
            'school_name' => ['nullable', 'string', 'max:150'],
            'major' => ['nullable', 'string', 'max:100'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'allergies' => ['nullable', 'string', 'max:500'],
            'medical_history' => ['nullable', 'string', 'max:1000'],
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'target_exam' => ['nullable', 'string', 'max:100'],
            'target_score' => ['nullable', 'string', 'max:50'],
        ];
    }
}
