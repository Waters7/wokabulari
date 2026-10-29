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
        return self::rulesFor($this->route('user'), $this->all());
    }

    /**
     * Pengguna internal boleh punya beberapa role sekaligus (mis. Tim Kerja + Pengajar).
     *
     * @param  array<string, mixed>  $data  isian form, untuk aturan unit tim kerja
     * @return array<string, mixed>
     */
    public static function rulesFor(?User $user = null, array $data = []): array
    {
        $roles = collect(Role::cases())->reject(fn (Role $role) => in_array($role, [Role::Participant, Role::Guardian], true))->map->value->all();

        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user?->id)],
            'username' => ['nullable', 'string', 'max:50', Rule::unique('users', 'username')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::in($roles)],
            // Role Tim Kerja wajib punya minimal satu unit.
            'units' => ['array', Rule::requiredIf(in_array(Role::Staff->value, (array) ($data['roles'] ?? []), true))],
            'units.*' => [Rule::enum(StaffUnit::class)],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8'],
            'is_active' => ['boolean'],
        ];
    }
}
