<?php

namespace App\Models;

use App\Enums\DormRequestCategory;
use App\Enums\DormRequestStatus;
use App\Enums\Priority;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class DormRequest extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'dorm_room_id', 'category', 'description', 'photo_path', 'priority', 'status', 'handled_by', 'resolution_note', 'resolved_at'];

    protected function casts(): array
    {
        return [
            'category' => DormRequestCategory::class,
            'status' => DormRequestStatus::class,
            'priority' => Priority::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(DormRoom::class, 'dorm_room_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? Storage::disk('private')->temporaryUrl($this->photo_path, now()->addMinutes(10)) : null;
    }
}
