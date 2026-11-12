<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepartureNotice extends Model
{
    protected $fillable = [
        'route_departure_id', 'air_departure_id', 'reservation_id',
        'channel', 'status', 'reason', 'recipient', 'sent_at',
    ];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
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
