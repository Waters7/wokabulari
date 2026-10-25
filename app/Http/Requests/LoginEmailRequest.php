<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LoginEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('updateLoginEmail', $this->user());
    }

    public function rules(): array
    {
        return self::rulesFor($this->user());
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => self::normalize($this->input('email'))]);
    }

    /** Email disimpan huruf kecil agar unik tanpa membedakan besar-kecil huruf. */
    public static function normalize(?string $email): ?string
    {
        return filled($email) ? Str::lower(trim($email)) : null;
    }

    /**
     * Email opsional yang bisa dipakai untuk login selain No. HP.
     *
     * @return array<string, mixed>
     */
    public static function rulesFor(User $user): array
    {
        return [
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messagesFor(): array
    {
        return ['email.unique' => 'Email ini sudah dipakai akun lain.'];
    }
}
