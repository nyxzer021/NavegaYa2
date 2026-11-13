<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscountRoute extends Model
{
    protected $fillable = ['discount_id', 'transport_route_id', 'air_route_id'];

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
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
