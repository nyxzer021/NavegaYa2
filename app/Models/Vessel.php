<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vessel extends Model
{
    protected static function booted(): void
    {
        static::created(fn (self $model) => $model->internal_code ?: $model->forceFill(['internal_code' => 'NAV-'.str_pad((string) $model->id, 6, '0', STR_PAD_LEFT)])->saveQuietly());
    }

    protected $fillable = [
        'organization_id', 'base_port_id', 'name', 'registration_number', 'vessel_type', 'hull_material',
        'seat_capacity', 'crew_capacity', 'gross_tonnage', 'length_m', 'beam_m', 'draft_m',
        'engine_description', 'manufacture_year', 'insurance_policy', 'dicapi_certificate_number', 'life_vest_count', 'emergency_equipment', 'insurance_expires_at',
        'inspection_expires_at', 'status', 'description', 'cover_image_path',
    ];

    protected function casts(): array
    {
        return [
            'seat_capacity' => 'integer', 'crew_capacity' => 'integer', 'manufacture_year' => 'integer', 'life_vest_count' => 'integer',
            'gross_tonnage' => 'decimal:2', 'length_m' => 'decimal:2', 'beam_m' => 'decimal:2', 'draft_m' => 'decimal:2',
            'insurance_expires_at' => 'date', 'inspection_expires_at' => 'date',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function basePort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'base_port_id');
    }

    public function seats(): HasMany
    {
        return $this->hasMany(VesselSeat::class);
    }

    public function departures(): HasMany
    {
        return $this->hasMany(RouteDeparture::class);
    }
}
