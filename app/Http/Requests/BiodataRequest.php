<?php

namespace App\Http\Requests;

use App\Enums\Gender;
use App\Rules\IndonesianMobile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BiodataRequest extends FormRequest
{
    public const SCHOOL_GRADES = ['X', 'XI', 'XII', 'XIII', 'Lulus'];

    public const BLOOD_TYPES = ['A', 'B', 'AB', 'O', 'Tidak tahu'];

    public const SHIRT_SIZES = ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL'];

    public function authorize(): bool
    {
        return $this->user()->can('updateBiodata', $this->user());
    }

    public function rules(): array
    {
        return self::rulesFor(filled($this->user()->profile?->gender));
    }

    /**
     * Biodata peserta. Jenis kelamin dikunci setelah terisi (dipakai pembagian kamar asrama).
     *
     * @return array<string, mixed>
     */
    public static function rulesFor(bool $genderLocked): array
    {
        return [
            'birth_place' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date', 'before:-10 years', 'after:-40 years'],
            'gender' => $genderLocked ? ['prohibited'] : ['nullable', Rule::enum(Gender::class)],
            'address' => ['nullable', 'string', 'max:500'],
            'school_name' => ['nullable', 'string', 'max:150'],
            'school_grade' => ['nullable', Rule::in(self::SCHOOL_GRADES)],
            'major' => ['nullable', 'string', 'max:100'],
            'blood_type' => ['nullable', Rule::in(self::BLOOD_TYPES)],
            'allergies' => ['nullable', 'string', 'max:500'],
            'medical_history' => ['nullable', 'string', 'max:1000'],
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20', new IndonesianMobile],
            'shirt_size' => ['nullable', Rule::in(self::SHIRT_SIZES)],
            'target_exam' => ['nullable', 'string', 'max:100'],
            'target_score' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributesFor(): array
    {
        return [
            'birth_place' => 'tempat lahir', 'birth_date' => 'tanggal lahir', 'gender' => 'jenis kelamin', 'address' => 'alamat',
            'school_name' => 'asal sekolah', 'school_grade' => 'kelas di sekolah', 'major' => 'jurusan', 'blood_type' => 'golongan darah',
            'allergies' => 'alergi / pantangan', 'medical_history' => 'riwayat penyakit', 'emergency_contact_name' => 'nama kontak darurat',
            'emergency_contact_phone' => 'No. HP kontak darurat', 'shirt_size' => 'ukuran baju', 'target_exam' => 'target ujian', 'target_score' => 'target skor',
        ];
    }
}
