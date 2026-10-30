<?php

namespace App\Models;

use App\Enums\LanguageAspect;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Master aspek penilaian periodik (mis. Berbicara, Kosakata, Hanzi). Pengajar memilih aspek per kelas.
 * language_id kosong = berlaku untuk semua bahasa; source_aspect = sumber saran nilai dari komponen sesi.
 */
class AssessmentAspect extends Model
{
    use HasFactory;

    protected $fillable = ['language_id', 'code', 'name', 'description', 'source_aspect', 'is_default', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'source_aspect' => LanguageAspect::class,
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(AssessmentScore::class);
    }

    public function classrooms(): BelongsToMany
    {
        return $this->belongsToMany(Classroom::class, 'classroom_assessment_aspect');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** Aspek umum (semua bahasa) ditambah aspek khusus bahasa tersebut. */
    public function scopeForLanguage(Builder $query, ?int $languageId): void
    {
        $query->where(fn ($q) => $q->whereNull('language_id')->when($languageId, fn ($q) => $q->orWhere('language_id', $languageId)));
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }

    /** Aspek bawaan sistem (punya kode) tidak bisa dihapus, hanya dinonaktifkan. */
    public function isBuiltIn(): bool
    {
        return $this->code !== null;
    }
}
