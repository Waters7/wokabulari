<?php

namespace App\Models;

use App\Enums\PointEvent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class GamificationRule extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = ['event_key', 'name', 'description', 'points', 'max_per_day', 'is_active'];

    protected function casts(): array
    {
        return [
            'event_key' => PointEvent::class,
            'points' => 'integer',
            'max_per_day' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['points', 'max_per_day', 'is_active'])->logOnlyDirty()->dontLogEmptyChanges()->useLogName('gamifikasi');
    }
}
