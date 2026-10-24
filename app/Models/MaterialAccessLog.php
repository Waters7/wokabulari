<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialAccessLog extends Model
{
    protected $fillable = ['session_material_id', 'user_id', 'viewed_at', 'downloaded_at'];

    protected function casts(): array
    {
        return [
            'viewed_at' => 'datetime',
            'downloaded_at' => 'datetime',
        ];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(SessionMaterial::class, 'session_material_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(SessionMaterial $material, User $user, bool $download = false): void
    {
        $log = static::query()->firstOrNew(['session_material_id' => $material->id, 'user_id' => $user->id]);
        $log->viewed_at ??= now();

        if ($download) {
            $log->downloaded_at = now();
        }

        $log->save();
    }
}
