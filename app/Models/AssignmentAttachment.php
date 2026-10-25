<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class AssignmentAttachment extends Model
{
    protected $fillable = ['assignment_id', 'file_path', 'original_name', 'mime', 'size'];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function url(): string
    {
        return Storage::disk('private')->temporaryUrl($this->file_path, now()->addMinutes(30));
    }
}
