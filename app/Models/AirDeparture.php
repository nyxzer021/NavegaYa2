<?php

namespace App\Models;

use App\Casts\AsLimaDateTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AirDeparture extends Model
{
    protected $fillable = ['air_route_id', 'aircraft_id', 'departure_at', 'boarding_starts_at', 'estimated_arrival_at', 'fare', 'cargo_enabled', 'status', 'is_published', 'notes', 'departure_series_id', 'previous_departure_at', 'disruption_type', 'disruption_reason', 'status_changed_by_user_id', 'status_changed_at', 'sales_paused_at', 'sales_pause_reason', 'sales_paused_by_user_id'];

    protected function casts(): array
    {
        return ['departure_at' => AsLimaDateTime::class, 'boarding_starts_at' => AsLimaDateTime::class, 'estimated_arrival_at' => AsLimaDateTime::class, 'previous_departure_at' => AsLimaDateTime::class, 'status_changed_at' => 'datetime', 'sales_paused_at' => 'datetime', 'fare' => 'decimal:2', 'cargo_enabled' => 'boolean', 'is_published' => 'boolean'];
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

    public function assignments(): HasMany
    {
        return $this->hasMany(DepartureAssignment::class);
    }
}
