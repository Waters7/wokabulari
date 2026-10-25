<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionOption extends Model
{
    protected $fillable = ['question_id', 'body', 'reading', 'is_correct', 'match_key', 'sort_order'];

    /** Kunci jawaban tidak pernah ikut terserialisasi. */
    protected $hidden = ['is_correct', 'match_key'];

    protected function casts(): array
    {
        return ['is_correct' => 'boolean'];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
