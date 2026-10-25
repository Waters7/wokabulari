<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class MealReceipt extends Model
{
    protected $fillable = ['meal_schedule_id', 'user_id', 'signature_path', 'received_at', 'latitude', 'longitude', 'proxy_by', 'note'];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(MealSchedule::class, 'meal_schedule_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function proxy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proxy_by');
    }

    public function signatureUrl(): string
    {
        return Storage::disk('private')->temporaryUrl($this->signature_path, now()->addMinutes(10));
    }

    public function isProxy(): bool
    {
        return $this->proxy_by !== null;
    }
}
