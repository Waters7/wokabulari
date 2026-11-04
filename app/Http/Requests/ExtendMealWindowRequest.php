<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Perpanjang waktu pengambilan konsumsi (makanan terlambat).
 */
class ExtendMealWindowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('meal.manage');
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
            'until' => ['required', 'date_format:H:i'],
            'note' => ['nullable', 'string', 'max:200'],
            'notify' => ['boolean'],
        ];
    }
}
