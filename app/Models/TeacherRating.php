<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherRating extends Model
{
    use HasFactory;

    public const ASPECTS = ['mastery' => 'Penguasaan materi', 'delivery' => 'Cara menyampaikan', 'discipline' => 'Kedisiplinan', 'interaction' => 'Interaksi'];

    protected $fillable = ['teacher_id', 'user_id', 'classroom_id', 'week_start', 'rating', 'mastery', 'delivery', 'discipline', 'interaction', 'comment', 'suggestion', 'is_anonymous'];

    /** Identitas penilai tidak ikut terserialisasi. */
    protected $hidden = ['user_id'];

    protected function casts(): array
    {
        return [
            'week_start' => DateOnly::class,
            'rating' => 'integer',
            'mastery' => 'integer',
            'delivery' => 'integer',
            'discipline' => 'integer',
            'interaction' => 'integer',
            'is_anonymous' => 'boolean',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /** Nama penilai sesuai hak lihat: pengajar tidak melihat nama bila anonim. */
    public function raterNameFor(User $viewer): string
    {
        if ($this->is_anonymous && ! $viewer->can('rating.view-names')) {
            return 'Anonim';
        }

        return $this->user?->name ?? 'Anonim';
    }
}
