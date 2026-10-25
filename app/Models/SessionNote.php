<?php

namespace App\Models;

use App\Enums\LanguageAspect;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionNote extends Model
{
    use HasFactory;

    protected $fillable = ['learning_session_id', 'user_id', 'teacher_id', 'aspect', 'note', 'visible_to_guardian', 'read_at'];

    protected function casts(): array
    {
        return [
            'aspect' => LanguageAspect::class,
            'visible_to_guardian' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(LearningSession::class, 'learning_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
