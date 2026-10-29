<?php

namespace App\Models;

use App\Enums\EvaluationAudience;
use App\Enums\EvaluationQuestionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvaluationQuestion extends Model
{
    use HasFactory;

    protected $fillable = ['program_evaluation_id', 'aspect', 'type', 'body', 'options', 'audience', 'is_required', 'sort_order'];

    protected function casts(): array
    {
        return [
            'type' => EvaluationQuestionType::class,
            'audience' => EvaluationAudience::class,
            'options' => 'array',
            'is_required' => 'boolean',
        ];
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(ProgramEvaluation::class, 'program_evaluation_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(EvaluationAnswer::class);
    }
}
