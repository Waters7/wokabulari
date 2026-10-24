<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Enums\Gender;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class ParticipantProfile extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'user_id', 'school_name', 'major', 'gender', 'birth_date', 'allergies', 'medical_history',
        'emergency_contact_name', 'emergency_contact_phone', 'face_reference_path', 'face_descriptor',
        'face_enrolled_at', 'target_exam', 'target_score',
    ];

    /** Descriptor wajah tidak pernah ikut terserialisasi ke browser. */
    protected $hidden = ['face_descriptor', 'medical_history'];

    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'birth_date' => DateOnly::class,
            'medical_history' => 'encrypted',
            'face_descriptor' => 'encrypted:array',
            'face_enrolled_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        // Data kesehatan & descriptor hanya dicatat perubahannya, tanpa nilai.
        return LogOptions::defaults()
            ->logOnly(['school_name', 'major', 'gender', 'allergies', 'emergency_contact_phone', 'face_enrolled_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('peserta');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasFaceReference(): bool
    {
        return ! empty($this->face_descriptor);
    }

    public function hasAllergies(): bool
    {
        return filled($this->allergies) && ! in_array(strtolower(trim($this->allergies)), ['-', 'tidak ada', 'tidak'], true);
    }
}
