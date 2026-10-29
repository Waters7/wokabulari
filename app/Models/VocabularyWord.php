<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu kata di bank kosakata (mis. 医生 · yīshēng · Dokter).
 */
class VocabularyWord extends Model
{
    use HasFactory;

    protected $fillable = ['language_id', 'word', 'reading', 'meaning', 'example', 'example_meaning', 'topic', 'sort_order', 'is_active', 'created_by'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $query->where(fn (Builder $q) => $q->where('word', 'like', "%{$term}%")
            ->orWhere('reading', 'like', "%{$term}%")
            ->orWhere('meaning', 'like', "%{$term}%")
            ->orWhere('topic', 'like', "%{$term}%"));
    }
}
