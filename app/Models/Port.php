<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Port extends Model
{
    protected static function booted(): void
    {
        static::created(fn (self $model) => $model->internal_code ?: $model->forceFill(['internal_code' => 'PTO-'.str_pad((string) $model->id, 6, '0', STR_PAD_LEFT)])->saveQuietly());
    }

    protected $fillable = ['department_id', 'province_id', 'district_id', 'name', 'modality', 'port_type', 'operator_name', 'contact_name', 'contact_phone', 'operating_hours', 'city', 'region', 'river', 'address', 'access_notes', 'available_services', 'latitude', 'longitude', 'is_active'];

    protected function casts(): array
    {
        return ['latitude' => 'decimal:7', 'longitude' => 'decimal:7', 'is_active' => 'boolean'];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(GeographicDepartment::class, 'department_id');
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(GeographicProvince::class, 'province_id');
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(GeographicDistrict::class, 'district_id');
    }

    public function originRoutes(): HasMany
    {
        return $this->hasMany(TransportRoute::class, 'origin_port_id');
    }

    public function destinationRoutes(): HasMany
    {
        return $this->hasMany(TransportRoute::class, 'destination_port_id');
    }
}
