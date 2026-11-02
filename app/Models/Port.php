<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Port extends Model
{
    /**
     * @var list<string>
     */
    public const AIR_PORT_TYPES = ['aerodromo', 'aeropuerto', 'pista'];

    protected static function booted(): void
    {
        static::created(fn (self $model) => $model->internal_code ?: $model->forceFill(['internal_code' => 'PTO-'.str_pad((string) $model->id, 6, '0', STR_PAD_LEFT)])->saveQuietly());
    }

    protected $fillable = ['department_id', 'province_id', 'district_id', 'name', 'modality', 'port_type', 'operator_name', 'contact_name', 'contact_phone', 'operating_hours', 'city', 'region', 'river', 'address', 'access_notes', 'available_services', 'latitude', 'longitude', 'is_active'];

    protected function casts(): array
    {
        return ['latitude' => 'decimal:7', 'longitude' => 'decimal:7', 'is_active' => 'boolean'];
    }

    public function placeName(): string
    {
        return $this->district?->name ?: ($this->city ?: 'Distrito por registrar');
    }

    public function optionLabel(): string
    {
        return trim($this->name).' · '.$this->placeName();
    }

    public function publicSide(): string
    {
        if ($this->modality === 'aereo' || in_array($this->port_type, self::AIR_PORT_TYPES, true)) {
            return 'aereo';
        }

        return 'fluvial';
    }

    public function scopePublicSide(Builder $query, string $side): Builder
    {
        if ($side === 'aereo') {
            return $query->where(function (Builder $air): void {
                $air->where('modality', 'aereo')->orWhereIn('port_type', self::AIR_PORT_TYPES);
            });
        }

        return $query->where(function (Builder $river): void {
            $river->where('modality', '!=', 'aereo')->orWhereNull('modality');
        })->where(function (Builder $river): void {
            $river->whereNull('port_type')->orWhereNotIn('port_type', self::AIR_PORT_TYPES);
        });
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

    public function originMasterRoutes(): HasMany
    {
        return $this->hasMany(MasterRoute::class, 'origin_port_id');
    }

    public function destinationMasterRoutes(): HasMany
    {
        return $this->hasMany(MasterRoute::class, 'destination_port_id');
    }

    public function baseVessels(): HasMany
    {
        return $this->hasMany(Vessel::class, 'base_port_id');
    }
}
