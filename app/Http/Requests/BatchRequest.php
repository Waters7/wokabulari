<?php

namespace App\Http\Requests;

use App\Models\Batch;
use Illuminate\Foundation\Http\FormRequest;

class BatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('batch.manage');
    }

    public function rules(): array
    {
        return self::rulesFor($this->route('batch'));
    }

    /**
     * @return array<string, mixed>
     */
    public static function rulesFor(?Batch $batch = null): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'attendance_target' => ['required', 'integer', 'between:1,100'],
            'passing_score' => ['required', 'numeric', 'between:0,100'],
            'is_active' => ['boolean'],
            // Jadwal absen harian angkatan
            'attendance_days' => ['required', 'array', 'min:1'],
            'attendance_days.*' => ['integer', 'between:1,7', 'distinct'],
            'attendance_opens_at' => ['required', 'date_format:H:i'],
            'attendance_late_after' => ['required', 'date_format:H:i', 'after_or_equal:attendance_opens_at'],
            'attendance_closes_at' => ['nullable', 'date_format:H:i', 'after:attendance_late_after'],
        ];
    }
}
