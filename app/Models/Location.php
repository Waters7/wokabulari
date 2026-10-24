<?php

namespace App\Models;

use App\Enums\LocationType;
use App\Support\Geo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Location extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = ['name', 'type', 'latitude', 'longitude', 'radius_m'];

    protected function casts(): array
    {
        return [
            'type' => LocationType::class,
            'latitude' => 'float',
            'longitude' => 'float',
            'radius_m' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges()->useLogName('pengaturan');
    }

    /** Jarak (meter) dari titik tertentu ke lokasi ini. */
    public function distanceTo(float $latitude, float $longitude): float
    {
        return Geo::distance($this->latitude, $this->longitude, $latitude, $longitude);
    }

    public function contains(float $latitude, float $longitude): bool
    {
        return $this->distanceTo($latitude, $longitude) <= $this->radius_m;
    }
}
