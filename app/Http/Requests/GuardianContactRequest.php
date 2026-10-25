<?php

namespace App\Http\Requests;

use App\Enums\GuardianRelationship;
use App\Rules\IndonesianMobile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardianContactRequest extends FormRequest
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
     * Kontak orang tua/wali yang wajib diisi peserta.
     *
     * @return array<string, mixed>
     */
    public static function rulesFor(): array
    {
        return [
            'guardian_name' => ['required', 'string', 'min:3', 'max:150'],
            'guardian_relationship' => ['required', Rule::enum(GuardianRelationship::class)],
            'guardian_phone' => ['required', 'string', 'max:20', new IndonesianMobile],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messagesFor(): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    public static function attributesFor(): array
    {
        return ['guardian_name' => 'nama orang tua/wali', 'guardian_relationship' => 'hubungan', 'guardian_phone' => 'No. HP orang tua/wali'];
    }
}
