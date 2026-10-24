<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DormRoomAssignment extends Model
{
    use HasFactory;

    protected $fillable = ['dorm_room_id', 'user_id', 'check_in_date', 'check_out_date'];

    protected function casts(): array
    {
        return [
            'check_in_date' => DateOnly::class,
            'check_out_date' => DateOnly::class,
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(DormRoom::class, 'dorm_room_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
