<?php

namespace App\Models;

use App\Services\ImageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MealReceipt extends Model
{
    protected $fillable = ['meal_schedule_id', 'user_id', 'signature_path', 'received_at', 'latitude', 'longitude', 'proxy_by', 'note'];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(MealSchedule::class, 'meal_schedule_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function proxy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proxy_by');
    }

    public function signatureUrl(): string
    {
        return Storage::disk('private')->temporaryUrl($this->signature_path, now()->addMinutes(10));
    }

    public function isProxy(): bool
    {
        return $this->proxy_by !== null;
    }

    /**
     * Path lokal gambar kecil tanda tangan (JPEG latar putih) untuk PDF tanda terima; dibuat sekali lalu dipakai ulang.
     * Null bila berkas tanda tangan tidak ada atau tidak bisa dibaca.
     */
    public function signatureThumbnail(): ?string
    {
        if (! $this->signature_path) {
            return null;
        }

        $disk = Storage::disk('private');
        $thumb = MealDelivery::thumbnailFile($this->signature_path);

        if (! $disk->exists($thumb)) {
            try {
                app(ImageService::class)->thumbnail($this->signature_path, $thumb, 240, 75);
            } catch (Throwable) {
                return null;
            }
        }

        return $disk->path($thumb);
    }
}
