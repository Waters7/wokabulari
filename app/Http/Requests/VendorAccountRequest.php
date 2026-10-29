<?php

namespace App\Http\Requests;

use App\Rules\IndonesianMobile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VendorAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('vendor.manage');
    }

    public function rules(): array
    {
        return self::rulesFor();
    }

    /**
     * Akun login vendor: login memakai No. HP (atau email bila diisi).
     *
     * @return array<string, mixed>
     */
    public static function rulesFor(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:20', new IndonesianMobile],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributesFor(): array
    {
        return ['name' => 'nama', 'phone' => 'No. HP', 'password' => 'kata sandi'];
    }
}
