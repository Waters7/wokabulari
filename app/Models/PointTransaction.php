<?php

namespace App\Models;

use App\Enums\PointEvent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PointTransaction extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'event_key', 'points', 'reference_type', 'reference_id', 'description', 'created_by', 'created_at'];

    protected function casts(): array
    {
        return [
            'event_key' => PointEvent::class,
            'points' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
