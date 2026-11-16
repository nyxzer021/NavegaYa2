<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouteDepartureSeatHold extends Model
{
    protected $fillable = ['route_departure_id', 'vessel_seat_id', 'kind'];

    public function departure(): BelongsTo
    {
        return $this->belongsTo(RouteDeparture::class, 'route_departure_id');
    }

    public function seat(): BelongsTo
    {
        return $this->belongsTo(VesselSeat::class, 'vessel_seat_id');
    }
}
