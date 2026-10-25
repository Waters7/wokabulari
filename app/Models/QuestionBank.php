<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuestionBank extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['teacher_id', 'language_id', 'name', 'level', 'topic'];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('id');
    }
}
