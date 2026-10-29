<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluationAnswer extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['evaluation_response_id', 'evaluation_question_id', 'value', 'score'];

    protected function casts(): array
    {
        return ['score' => 'integer'];
    }

    public function response(): BelongsTo
    {
        return $this->belongsTo(EvaluationResponse::class, 'evaluation_response_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(EvaluationQuestion::class, 'evaluation_question_id');
    }
}
