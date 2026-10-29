<?php

namespace App\Http\Requests;

use App\Enums\EvaluationAudience;
use App\Enums\EvaluationCategory;
use App\Enums\EvaluationQuestionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProgramEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('evaluation.manage');
    }

    public function rules(): array
    {
        return self::rulesFor();
    }

    /**
     * Survei beserta sasaran (angkatan, peserta/pengajar), tenggat, dan pertanyaannya.
     *
     * @return array<string, mixed>
     */
    public static function rulesFor(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['required', Rule::enum(EvaluationCategory::class)],
            'batch_ids' => ['required', 'array', 'min:1'],
            'batch_ids.*' => ['integer', 'distinct', 'exists:batches,id'],
            'target_participants' => ['boolean'],
            'target_teachers' => ['boolean'],
            'is_anonymous' => ['boolean'],
            'opens_at' => ['nullable', 'date'],
            // Tenggat setelah waktu dibuka dicek di SaveEvaluation (opens_at boleh kosong).
            'closes_at' => ['required', 'date', 'after:now'],
            'questions' => ['array'],
            'questions.*.aspect' => ['nullable', 'string', 'max:100'],
            'questions.*.type' => ['required', Rule::enum(EvaluationQuestionType::class)],
            'questions.*.body' => ['required', 'string', 'max:500'],
            'questions.*.options' => ['nullable', 'string', 'max:2000'],
            'questions.*.audience' => ['required', Rule::enum(EvaluationAudience::class)],
            'questions.*.is_required' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messagesFor(): array
    {
        return [
            'batch_ids.required' => 'Pilih minimal satu angkatan.',
            'closes_at.after' => 'Tenggat harus di masa depan.',
            'questions.*.body.required' => 'Pertanyaan wajib diisi.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributesFor(): array
    {
        return ['title' => 'judul', 'category' => 'jenis', 'opens_at' => 'waktu dibuka', 'closes_at' => 'tenggat'];
    }
}
