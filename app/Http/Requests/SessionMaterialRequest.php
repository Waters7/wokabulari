<?php

namespace App\Http\Requests;

use App\Enums\MaterialVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SessionMaterialRequest extends FormRequest
{
    /** Ekstensi yang diizinkan untuk materi berkas. */
    public const MIMES = 'pdf,ppt,pptx,doc,docx,xls,xlsx,jpg,jpeg,png,webp,mp3,m4a,wav,ogg,mp4,webm';

    public function authorize(): bool
    {
        return $this->user()->can('material.manage');
    }

    public function rules(): array
    {
        return self::rulesFor($this->input('kind', 'file'));
    }

    /**
     * @return array<string, mixed>
     */
    public static function rulesFor(string $kind): array
    {
        $maxKb = (int) setting('files.max_upload_mb', 50) * 1024;

        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'kind' => ['required', Rule::in(['file', 'link'])],
            'file' => $kind === 'file' ? ['required', 'file', 'mimes:'.self::MIMES, 'max:'.$maxKb] : ['nullable'],
            'url' => $kind === 'link' ? ['required', 'url:https,http', 'max:500'] : ['nullable'],
            'visibility' => ['required', Rule::enum(MaterialVisibility::class)],
            'visible_from' => ['nullable', 'date', 'required_if:visibility,scheduled'],
            'is_downloadable' => ['boolean'],
            'level' => ['nullable', 'string', 'max:50'],
            'topic' => ['nullable', 'string', 'max:200'],
        ];
    }
}
