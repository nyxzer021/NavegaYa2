<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouteStop extends Model
{
    protected $fillable = ['transport_route_id', 'air_route_id', 'port_id', 'position', 'minutes_from_origin'];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'minutes_from_origin' => 'integer',
        ];
    }

    public function port(): BelongsTo
    {
        return $this->belongsTo(Port::class);
    }

    public function transportRoute(): BelongsTo
    {
        return $this->belongsTo(TransportRoute::class);
    }

    public function airRoute(): BelongsTo
    {
        return $this->belongsTo(AirRoute::class);
    }
}
