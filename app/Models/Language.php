<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Language extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'exam_format', 'reading_label'];

    public function classrooms(): HasMany
    {
        return $this->hasMany(Classroom::class);
    }

    /** Bahasa dengan aksara non-Latin butuh font CJK. */
    public function isCjk(): bool
    {
        return in_array($this->code, ['zh', 'ja'], true);
    }

    public function flag(): string
    {
        return match ($this->code) {
            'en' => '🇬🇧',
            'zh' => '🇨🇳',
            'ja' => '🇯🇵',
            'de' => '🇩🇪',
            default => '🌐',
        };
    }
}
