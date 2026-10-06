<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ReservationSeat extends Model
{
    protected $fillable = ['reservation_id', 'route_departure_id', 'air_departure_id', 'vessel_seat_id', 'aircraft_seat_id', 'passenger_name', 'document_type', 'document_number', 'passenger_age'];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function departure(): BelongsTo
    {
        return $this->belongsTo(RouteDeparture::class, 'route_departure_id');
    }

    public function seat(): BelongsTo
    {
        return $this->belongsTo(VesselSeat::class, 'vessel_seat_id');
    }

    public function aircraftSeat(): BelongsTo
    {
        return $this->belongsTo(AircraftSeat::class, 'aircraft_seat_id');
    }

    public function ticket(): HasOne
    {
        return $this->hasOne(Ticket::class);
    }
}
