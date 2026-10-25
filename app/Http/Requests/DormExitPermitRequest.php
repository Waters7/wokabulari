<?php

namespace App\Http\Requests;

use App\Enums\ExitPermitType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DormExitPermitRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public static function rulesFor(): array
    {
        return [
            'type' => ['required', Rule::enum(ExitPermitType::class)],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'destination' => ['required', 'string', 'max:255'],
            'companion' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['required', 'string', 'regex:/^[0-9+\-\s]{8,20}$/'],
            'planned_departure' => ['required', 'date'],
            'planned_return' => ['required', 'date', 'after:planned_departure', 'before_or_equal:'.now()->addDays(30)->toDateString()],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributesFor(): array
    {
        return [
            'type' => 'jenis izin', 'reason' => 'alasan', 'destination' => 'tujuan', 'companion' => 'pendamping',
            'contact_phone' => 'nomor kontak', 'planned_departure' => 'rencana keluar', 'planned_return' => 'rencana kembali', 'attachment' => 'lampiran',
        ];
    }
}
