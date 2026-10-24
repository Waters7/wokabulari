<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Enums\StaffUnit;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Pengguna non-peserta: super admin, pimpinan, admin program, pengajar, tim kerja.
 */
class StaffUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('user.manage');
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
        $roles = collect(Role::cases())->reject(fn (Role $role) => in_array($role, [Role::Participant, Role::Guardian], true))->map->value->all();

        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user?->id)],
            'username' => ['nullable', 'string', 'max:50', Rule::unique('users', 'username')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::in($roles)],
            'units' => ['array', 'required_if:roles.*,staff'],
            'units.*' => [Rule::enum(StaffUnit::class)],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8'],
            'is_active' => ['boolean'],
        ];
    }
}
