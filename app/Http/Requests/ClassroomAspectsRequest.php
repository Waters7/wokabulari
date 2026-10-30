<?php

namespace App\Http\Requests;

use App\Models\Assessment;
use App\Models\Classroom;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Pilihan aspek penilaian periodik untuk satu kelas.
 */
class ClassroomAspectsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', [Assessment::class, $this->route('classroom')]);
    }

    public function rules(): array
    {
        return self::rulesFor($this->route('classroom'));
    }

    /**
     * @return array<string, mixed>
     */
    public static function rulesFor(Classroom $classroom): array
    {
        return [
            'aspects' => ['required', 'array', 'min:1', 'max:20'],
            'aspects.*' => ['integer', Rule::exists('assessment_aspects', 'id')->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('language_id')->orWhere('language_id', $classroom->language_id))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messagesFor(): array
    {
        return ['aspects.required' => 'Pilih minimal satu aspek.', 'aspects.min' => 'Pilih minimal satu aspek.'];
    }
}
