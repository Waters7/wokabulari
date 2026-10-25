<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorRating extends Model
{
    use HasFactory;

    public const ASPECTS = ['taste' => 'Rasa', 'portion' => 'Porsi', 'cleanliness' => 'Kebersihan', 'punctuality' => 'Ketepatan waktu'];

    protected $fillable = ['vendor_id', 'user_id', 'week_start', 'rating', 'taste', 'portion', 'cleanliness', 'punctuality', 'comment', 'suggestion'];

    protected function casts(): array
    {
        return [
            'week_start' => DateOnly::class,
            'rating' => 'integer',
            'taste' => 'integer',
            'portion' => 'integer',
            'cleanliness' => 'integer',
            'punctuality' => 'integer',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
