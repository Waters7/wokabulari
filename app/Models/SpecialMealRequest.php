<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Enums\MealType;
use App\Enums\SpecialMealStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpecialMealRequest extends Model
{
    protected $fillable = ['user_id', 'health_report_id', 'date', 'meal_type', 'request', 'delivered_to_room', 'status', 'handled_by'];

    protected function casts(): array
    {
        return [
            'date' => DateOnly::class,
            'meal_type' => MealType::class,
            'status' => SpecialMealStatus::class,
            'delivered_to_room' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
