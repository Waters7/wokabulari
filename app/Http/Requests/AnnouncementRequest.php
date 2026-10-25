<?php

namespace App\Http\Requests;

use App\Enums\AnnouncementAudience;
use App\Enums\AnnouncementSource;
use App\Enums\Role;
use App\Models\Announcement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Announcement::class);
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
            'title' => ['required', 'string', 'min:3', 'max:150'],
            'body' => ['required', 'string', 'min:5', 'max:5000'],
            'source' => ['required', Rule::enum(AnnouncementSource::class)],
            'audience' => ['required', Rule::enum(AnnouncementAudience::class)],
            'target_id' => ['nullable', 'integer'],
            'target_role' => ['nullable', Rule::enum(Role::class)],
            'is_pinned' => ['boolean'],
            'notify_whatsapp' => ['boolean'],
            'publish' => ['required', Rule::in(['now', 'schedule', 'draft'])],
            'publish_at' => ['nullable', 'required_if:publish,schedule', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributesFor(): array
    {
        return ['title' => 'judul', 'body' => 'isi', 'source' => 'sumber', 'audience' => 'sasaran', 'target_id' => 'sasaran', 'target_role' => 'peran', 'publish_at' => 'waktu terbit'];
    }
}
