<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepartureAssignment extends Model
{
    protected $fillable = ['crew_member_id', 'route_departure_id', 'air_departure_id', 'duty'];

    public function member(): BelongsTo
    {
        return $this->belongsTo(CrewMember::class, 'crew_member_id');
    }

    public function routeDeparture(): BelongsTo
    {
        return $this->belongsTo(RouteDeparture::class);
    }

    public function airDeparture(): BelongsTo
    {
        return $this->belongsTo(AirDeparture::class);
    }
}
