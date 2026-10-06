<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AirDeparture extends Model
{
    protected $fillable = ['air_route_id', 'aircraft_id', 'departure_at', 'boarding_starts_at', 'estimated_arrival_at', 'fare', 'cargo_enabled', 'status', 'is_published', 'notes'];

    protected function casts(): array
    {
        return ['departure_at' => 'datetime', 'boarding_starts_at' => 'datetime', 'estimated_arrival_at' => 'datetime', 'fare' => 'decimal:2', 'cargo_enabled' => 'boolean', 'is_published' => 'boolean'];
    }

    public function airRoute(): BelongsTo
    {
        return $this->belongsTo(AirRoute::class);
    }

    public function aircraft(): BelongsTo
    {
        return $this->belongsTo(Aircraft::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }
}
