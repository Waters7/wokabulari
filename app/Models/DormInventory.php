<?php

namespace App\Models;

use App\Enums\ItemCondition;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class DormInventory extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = ['dorm_room_id', 'item_name', 'quantity', 'condition', 'note'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'condition' => ItemCondition::class,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges()->useLogName('asrama');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(DormRoom::class, 'dorm_room_id');
    }
}
