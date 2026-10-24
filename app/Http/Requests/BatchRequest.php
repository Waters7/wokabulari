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
        ];
    }
}
