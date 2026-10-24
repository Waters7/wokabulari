<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Payload absen dari browser (kamera + GPS).
 */
class DailyAttendanceRequest extends FormRequest
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
            'descriptor' => ['nullable', 'array', 'size:128'],
            'descriptor.*' => ['numeric', 'between:-1,1'],
            'selfie' => ['nullable', 'string', 'max:4000000', 'starts_with:data:image/'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
            'device' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function approvalRules(): array
    {
        return [
            'status' => ['required', 'in:present,late,excused,sick,absent'],
            'note' => ['required', 'string', 'min:5', 'max:255'],
        ];
    }
}
