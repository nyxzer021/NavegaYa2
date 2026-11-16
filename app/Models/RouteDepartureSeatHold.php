<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouteDepartureSeatHold extends Model
{
    protected $fillable = ['route_departure_id', 'vessel_seat_id', 'kind', 'agency_id', 'agency_lot_id'];

    public function departure(): BelongsTo
    {
        return $this->belongsTo(RouteDeparture::class, 'route_departure_id');
    }

    public function seat(): BelongsTo
    {
        return $this->belongsTo(VesselSeat::class, 'vessel_seat_id');
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(AgencyLot::class, 'agency_lot_id');
    }
}
