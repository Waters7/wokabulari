<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionAttendanceCode extends Model
{
    use HasFactory;

    /** Tanpa karakter ambigu (O/0, I/1/L). */
    public const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    protected $fillable = ['learning_session_id', 'code', 'expires_at', 'is_active', 'generated_by'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(LearningSession::class, 'learning_session_id');
    }

    public function isUsable(): bool
    {
        return $this->is_active && $this->expires_at->isFuture();
    }

    public static function randomCode(): string
    {
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $code;
    }
}
