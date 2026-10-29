<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Enums\MealType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class MealSchedule extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = ['date', 'meal_type', 'vendor_id', 'menu', 'starts_at', 'ends_at', 'portions_ordered', 'created_by'];

    protected function casts(): array
    {
        return [
            'date' => DateOnly::class,
            'meal_type' => MealType::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'portions_ordered' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['date', 'meal_type', 'vendor_id', 'menu', 'portions_ordered'])->logOnlyDirty()->dontLogEmptyChanges()->useLogName('konsumsi');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(MealReceipt::class);
    }

    /** Laporan pengantaran vendor (jam siap, tempat, foto). */
    public function delivery(): HasOne
    {
        return $this->hasOne(MealDelivery::class);
    }

    public function scopeOnDate(Builder $query, $date): void
    {
        $query->whereDate('date', $date);
    }

    /** Jadwal yang sedang dalam rentang pengambilan. */
    public function scopeActiveNow(Builder $query): void
    {
        $query->where('starts_at', '<=', now())->where('ends_at', '>=', now());
    }

    public function isOpen(): bool
    {
        return now()->betweenIncluded($this->starts_at, $this->ends_at);
    }

    public function windowLabel(): string
    {
        return jam($this->starts_at).'–'.jam($this->ends_at);
    }
}
