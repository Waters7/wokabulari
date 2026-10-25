<?php

namespace App\Models;

use App\Enums\QuestionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Question extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['question_bank_id', 'type', 'body', 'reading', 'media_path', 'points', 'explanation'];

    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'points' => 'float',
        ];
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(QuestionBank::class, 'question_bank_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->orderBy('sort_order')->orderBy('id');
    }

    public function quizzes(): BelongsToMany
    {
        return $this->belongsToMany(Quiz::class, 'quiz_question')->withPivot(['sort_order', 'points', 'section']);
    }

    public function mediaUrl(): ?string
    {
        return $this->media_path ? Storage::disk('private')->temporaryUrl($this->media_path, now()->addHours(3)) : null;
    }
}
