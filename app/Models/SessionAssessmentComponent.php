<?php

namespace App\Models;

use App\Enums\ComponentSource;
use App\Enums\LanguageAspect;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class SessionAssessmentComponent extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = ['learning_session_id', 'name', 'aspect', 'weight', 'max_score', 'source', 'source_id'];

    protected function casts(): array
    {
        return [
            'aspect' => LanguageAspect::class,
            'source' => ComponentSource::class,
            'weight' => 'float',
            'max_score' => 'float',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'aspect', 'weight', 'max_score'])->logOnlyDirty()->dontLogEmptyChanges()->useLogName('nilai');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(LearningSession::class, 'learning_session_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(SessionScore::class);
    }

    /** Komponen dari quiz/tugas diisi otomatis dan tidak bisa diedit manual. */
    public function isEditable(): bool
    {
        return $this->source === ComponentSource::Manual;
    }
}
