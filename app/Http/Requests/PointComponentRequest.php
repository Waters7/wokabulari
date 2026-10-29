<?php

namespace App\Http\Requests;

use App\Enums\PointCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PointComponentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('gamification.manage');
    }

    public function rules(): array
    {
        return self::rulesFor();
    }

    /**
     * Komponen tambahan oleh admin (poin boleh negatif untuk pelanggaran).
     *
     * @return array<string, mixed>
     */
    public static function rulesFor(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'category' => ['required', Rule::enum(PointCategory::class)],
            'points' => ['required', 'integer', 'between:-100,200', 'not_in:0'],
            'max_per_day' => ['nullable', 'integer', 'between:1,50'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Pengajuan pengajar: hanya menambah poin, kategori komponen tambahan.
     *
     * @return array<string, mixed>
     */
    public static function proposalRules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'category' => ['required', Rule::enum(PointCategory::class)->only(PointCategory::forCustom())],
            'points' => ['required', 'integer', 'between:1,100'],
            'description' => ['required', 'string', 'min:10', 'max:255'],
        ];
    }

    /**
     * Pemberian komponen ke peserta satu kelas.
     *
     * @return array<string, mixed>
     */
    public static function awardRules(): array
    {
        return [
            'component_id' => ['required', 'integer', Rule::exists('gamification_rules', 'id')->where('is_custom', true)],
            'classroom_id' => ['required', 'integer', Rule::exists('classrooms', 'id')],
            'participant_ids' => ['required', 'array', 'min:1'],
            'participant_ids.*' => ['integer'],
            'assignment_id' => ['nullable', 'integer'],
            'quiz_id' => ['nullable', 'integer'],
            'note' => ['nullable', 'string', 'max:150'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributesFor(): array
    {
        return [
            'name' => 'nama komponen', 'category' => 'kategori', 'points' => 'poin', 'max_per_day' => 'maks. per hari',
            'description' => 'keterangan', 'component_id' => 'komponen', 'classroom_id' => 'kelas',
            'participant_ids' => 'peserta', 'assignment_id' => 'tugas', 'quiz_id' => 'quiz', 'note' => 'catatan',
        ];
    }
}
