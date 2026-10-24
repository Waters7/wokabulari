<?php

namespace App\Models;

use App\Enums\StaffUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffUnitAssignment extends Model
{
    protected $table = 'staff_units';

    protected $fillable = ['user_id', 'unit'];

    protected function casts(): array
    {
        return ['unit' => StaffUnit::class];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
