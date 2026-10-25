<?php

namespace App\Models;

use App\Enums\LanguageAspect;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentComponentTemplate extends Model
{
    use HasFactory;

    protected $fillable = ['teacher_id', 'name', 'aspect', 'weight', 'max_score'];

    protected function casts(): array
    {
        return [
            'aspect' => LanguageAspect::class,
            'weight' => 'float',
            'max_score' => 'float',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
