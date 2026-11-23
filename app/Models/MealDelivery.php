<?php

namespace App\Models;

use App\Enums\DeliveryUnit;
use App\Services\ImageService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Throwable;

/**
 * Input konsumsi oleh tim konsumsi: vendor, jumlah, menu, jam siap diambil, tempat, dan foto dokumentasi.
 */
class MealDelivery extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'meal_schedule_id', 'vendor_id', 'reported_by', 'quantity', 'unit', 'menu', 'ready_at',
        'location', 'menu_photo_path', 'total_photo_path', 'notified_at',
    ];

    protected function casts(): array
    {
        return [
            'unit' => DeliveryUnit::class,
            'quantity' => 'integer',
            'ready_at' => 'datetime',
            'notified_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['quantity', 'unit', 'menu', 'ready_at', 'location'])->logOnlyDirty()->dontLogEmptyChanges()->useLogName('konsumsi');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(MealSchedule::class, 'meal_schedule_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function isReady(): bool
    {
        return $this->ready_at->lte(now());
    }

    /** Mis. "120 pcs". */
    public function quantityLabel(): string
    {
        return angka($this->quantity).' '.$this->unit->label();
    }

    /** Path foto dokumentasi: 'menu' atau 'total'. */
    public function photoPath(string $type): ?string
    {
        return $type === 'menu' ? $this->menu_photo_path : $this->total_photo_path;
    }

    /** Tautan sementara foto dokumentasi (disk privat). */
    public function photoUrl(string $type): ?string
    {
        $path = $this->photoPath($type);

        return $path ? Storage::disk('private')->temporaryUrl($path, now()->addMinutes(30)) : null;
    }

    /** Lokasi gambar kecil sebuah foto di disk private (dibuat saat pertama dibutuhkan). */
    public static function thumbnailFile(string $path): string
    {
        return dirname($path).'/thumbs/'.pathinfo($path, PATHINFO_FILENAME).'.jpg';
    }

    /** Path lokal gambar kecil untuk disematkan di Excel; null bila tidak ada foto atau foto tidak bisa dibaca. */
    public function thumbnailPath(string $type): ?string
    {
        $path = $this->photoPath($type);
        if (! $path) {
            return null;
        }

        $disk = Storage::disk('private');
        $thumb = self::thumbnailFile($path);

        if (! $disk->exists($thumb)) {
            try {
                app(ImageService::class)->thumbnail($path, $thumb);
            } catch (Throwable) {
                return null;
            }
        }

        return $disk->path($thumb);
    }
}
