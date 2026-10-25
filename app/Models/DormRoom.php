<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DormRoom extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['dormitory_id', 'number', 'floor', 'capacity'];

    protected function casts(): array
    {
        return ['capacity' => 'integer'];
    }

    public function dormitory(): BelongsTo
    {
        return $this->belongsTo(Dormitory::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DormRoomAssignment::class);
    }

    public function activeAssignments(): HasMany
    {
        return $this->hasMany(DormRoomAssignment::class)->whereNull('check_out_date');
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(DormInventory::class);
    }

    public function label(): string
    {
        return 'Kamar '.$this->number;
    }
}
