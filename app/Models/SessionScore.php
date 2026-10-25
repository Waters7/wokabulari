<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class SessionScore extends Model
{
    use LogsActivity;

    protected $fillable = ['session_assessment_component_id', 'user_id', 'score', 'note'];

    protected function casts(): array
    {
        return ['score' => 'float'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['score', 'note'])->logOnlyDirty()->dontLogEmptyChanges()->useLogName('nilai');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(SessionAssessmentComponent::class, 'session_assessment_component_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
