<?php

namespace App\Http\Requests;

use App\Enums\DocumentationSource;
use App\Enums\SessionPhase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Unggahan dokumentasi kelas: foto kamera atau file (gambar/PDF) maks. 10 MB.
 */
class SessionDocumentationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('session'));
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
            'file' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,heic,heif,pdf'],
            'source' => ['required', Rule::enum(DocumentationSource::class)],
            'phase' => ['required', Rule::enum(SessionPhase::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messagesFor(): array
    {
        return [
            'file.required' => 'Ambil foto kelas atau pilih file dokumentasi dulu.',
            'file.mimes' => 'File harus berupa foto (JPG, PNG, WEBP, HEIC) atau PDF.',
            'file.max' => 'Ukuran file maksimal 10 MB.',
        ];
    }
}
