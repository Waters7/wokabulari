<?php

namespace App\Http\Requests;

use App\Enums\Gender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkDormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('dormitory.manage');
    }

    public function rules(): array
    {
        return self::rulesFor();
    }

    /**
     * Atur asrama banyak peserta: isi kamar otomatis, pindah ke satu kamar, atau ubah status tinggal.
     *
     * @return array<string, mixed>
     */
    public static function rulesFor(): array
    {
        return [
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'mode' => ['required', Rule::in(['auto', 'room', 'resident', 'non_resident', 'gender'])],
            'room_id' => ['required_if:mode,room', 'nullable', 'exists:dorm_rooms,id'],
            'gender' => ['required_if:mode,gender', 'nullable', Rule::enum(Gender::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messagesFor(): array
    {
        return [
            'user_ids.required' => 'Pilih peserta dulu.',
            'room_id.required_if' => 'Pilih kamar tujuan.',
            'gender.required_if' => 'Pilih jenis kelamin.',
        ];
    }
}
