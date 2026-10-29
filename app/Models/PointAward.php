<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Satu kali pemberian komponen poin tambahan ke sejumlah peserta (mis. "Presentasi terbaik" untuk 3 peserta).
 */
class PointAward extends Model
{
    use HasFactory;

    protected $fillable = ['gamification_rule_id', 'classroom_id', 'assignment_id', 'quiz_id', 'points', 'note', 'awarded_by'];

    protected function casts(): array
    {
        return ['points' => 'integer'];
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(GamificationRule::class, 'gamification_rule_id');
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function awarder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'awarded_by');
    }

    public function transactions(): MorphMany
    {
        return $this->morphMany(PointTransaction::class, 'reference');
    }

    /** Keterangan di riwayat poin peserta, mis. "Presentasi terbaik · Tugas: Perkenalan diri". */
    public function description(): string
    {
        $context = match (true) {
            $this->assignment !== null => 'Tugas: '.$this->assignment->title,
            $this->quiz !== null => 'Quiz: '.$this->quiz->title,
            default => null,
        };

        return collect([$this->component->name, $context, $this->note])->filter()->join(' · ');
    }
}
