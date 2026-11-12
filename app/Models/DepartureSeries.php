<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DepartureSeries extends Model
{
    protected $fillable = [
        'organization_id', 'transport_route_id', 'air_route_id', 'vessel_id', 'aircraft_id',
        'weekdays', 'departure_time', 'starts_on', 'ends_on', 'boarding_offset_minutes',
        'fare', 'cargo_enabled', 'notes', 'status',
    ];

    protected function casts(): array
    {
        return [
            'weekdays' => 'array',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'boarding_offset_minutes' => 'integer',
            'fare' => 'decimal:2',
            'cargo_enabled' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function transportRoute(): BelongsTo
    {
        return $this->belongsTo(TransportRoute::class);
    }

    public function airRoute(): BelongsTo
    {
        return $this->belongsTo(AirRoute::class);
    }

    public function vessel(): BelongsTo
    {
        return $this->belongsTo(Vessel::class);
    }

    public function aircraft(): BelongsTo
    {
        return $this->belongsTo(Aircraft::class);
    }

    public function riverDepartures(): HasMany
    {
        return $this->hasMany(RouteDeparture::class);
    }

    public function airDepartures(): HasMany
    {
        return $this->hasMany(AirDeparture::class);
    }
}
