<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nilai satu aspek pada penilaian periodik (0–100).
 */
class AssessmentScore extends Model
{
    use HasFactory;

    protected $fillable = ['assessment_id', 'assessment_aspect_id', 'score'];

    protected function casts(): array
    {
        return ['score' => 'float'];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function aspect(): BelongsTo
    {
        return $this->belongsTo(AssessmentAspect::class, 'assessment_aspect_id');
    }
}
