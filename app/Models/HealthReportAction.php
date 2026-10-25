<?php

namespace App\Models;

use App\Enums\HealthActionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HealthReportAction extends Model
{
    protected $fillable = ['health_report_id', 'handled_by', 'action_type', 'medicine', 'dosage', 'referral_place', 'condition_note', 'next_check_at', 'reminded_at', 'acted_at'];

    protected $hidden = ['condition_note'];

    protected function casts(): array
    {
        return [
            'action_type' => HealthActionType::class,
            'condition_note' => 'encrypted',
            'next_check_at' => 'datetime',
            'reminded_at' => 'datetime',
            'acted_at' => 'datetime',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(HealthReport::class, 'health_report_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
