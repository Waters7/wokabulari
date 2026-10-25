<?php

namespace App\Http\Requests;

use App\Enums\LanguageAspect;
use App\Enums\SubmissionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assignment.manage');
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
            'classroom_id' => ['required', 'exists:classrooms,id'],
            'learning_session_id' => ['nullable', 'exists:learning_sessions,id'],
            'title' => ['required', 'string', 'max:200'],
            'instructions' => ['required', 'string', 'max:5000'],
            'submission_type' => ['required', Rule::enum(SubmissionType::class)],
            'aspect' => ['required', Rule::enum(LanguageAspect::class)],
            'due_at' => ['required', 'date'],
            'max_score' => ['required', 'numeric', 'min:1', 'max:1000'],
            'allow_late' => ['boolean'],
            'late_penalty_percent' => ['required', 'integer', 'between:0,100'],
            'allow_resubmit' => ['boolean'],
            'attachments.*' => ['file', 'mimes:pdf,doc,docx,ppt,pptx,jpg,jpeg,png,webp,mp3,m4a,wav,mp4', 'max:'.((int) setting('files.max_upload_mb', 50) * 1024)],
        ];
    }

    /**
     * Aturan berkas pengumpulan peserta.
     *
     * @return array<string, mixed>
     */
    public static function submissionRules(): array
    {
        $maxKb = (int) setting('files.max_upload_mb', 50) * 1024;

        return [
            'text' => ['nullable', 'string', 'max:20000'],
            'files' => ['array', 'max:5'],
            'files.*' => ['file', 'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,txt,jpg,jpeg,png,webp,mp3,m4a,wav,ogg,webm,mp4', 'max:'.$maxKb],
            'photos' => ['array', 'max:10'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:15360'],
            'audio' => ['nullable', 'file', 'mimetypes:audio/webm,audio/ogg,audio/mpeg,audio/mp4,audio/x-m4a,audio/aac,audio/wav,audio/x-wav,video/webm,video/mp4,application/octet-stream', 'max:20480'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function gradeRules(float $maxScore): array
    {
        return [
            'score' => ['required', 'numeric', 'min:0', 'max:'.$maxScore],
            'feedback' => ['nullable', 'string', 'max:5000'],
            'voiceNote' => ['nullable', 'file', 'mimetypes:audio/webm,audio/ogg,audio/mpeg,audio/mp4,audio/x-m4a,audio/wav,video/webm,video/mp4,application/octet-stream', 'max:10240'],
        ];
    }
}
