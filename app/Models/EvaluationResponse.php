<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvaluationResponse extends Model
{
    use HasFactory;

    protected $fillable = ['program_evaluation_id', 'user_id', 'respondent_hash', 'respondent_role', 'batch_id', 'submitted_at'];

    /** Hash responden tidak pernah dikirim ke browser. */
    protected $hidden = ['respondent_hash'];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime'];
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(ProgramEvaluation::class, 'program_evaluation_id');
    }

    /** Null pada survei anonim. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(EvaluationAnswer::class);
    }
}
