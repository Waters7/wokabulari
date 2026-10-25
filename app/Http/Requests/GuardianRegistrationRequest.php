<?php

namespace App\Http\Requests;

use App\Enums\GuardianRelationship;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class GuardianRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return self::rulesFor();
    }

    /**
     * Pendaftaran akun wali dengan kode undangan.
     *
     * @return array<string, mixed>
     */
    public static function rulesFor(): array
    {
        return [
            'code' => ['required', 'string', 'max:12'],
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'phone' => ['required', 'string', 'regex:/^(\+?62|0)8[0-9]{7,12}$/'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')],
            'relationship' => ['required', Rule::enum(GuardianRelationship::class)],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ];
    }

    /**
     * Admin membuat undangan wali untuk peserta.
     *
     * @return array<string, mixed>
     */
    public static function invitationRules(): array
    {
        return [
            'participant_id' => ['required', Rule::exists('users', 'id')],
            'phone' => ['nullable', 'string', 'regex:/^(\+?62|0)8[0-9]{7,12}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'relationship' => ['nullable', Rule::enum(GuardianRelationship::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributesFor(): array
    {
        return ['code' => 'kode undangan', 'name' => 'nama', 'phone' => 'nomor HP', 'relationship' => 'hubungan', 'password' => 'kata sandi', 'participant_id' => 'peserta'];
    }
}
