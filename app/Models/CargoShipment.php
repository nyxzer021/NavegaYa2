<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CargoShipment extends Model
{
    protected $fillable = ['route_departure_id', 'air_departure_id', 'reservation_id', 'reservation_seat_id', 'code', 'sender_name', 'sender_document', 'sender_phone', 'recipient_name', 'recipient_document', 'recipient_phone', 'description', 'package_count', 'declared_weight_kg', 'verified_weight_kg', 'rate_per_kg', 'amount', 'payment_status', 'status', 'receipt_number', 'received_at', 'delivered_at'];

    protected function casts(): array
    {
        return ['declared_weight_kg' => 'decimal:2', 'verified_weight_kg' => 'decimal:2', 'rate_per_kg' => 'decimal:2', 'amount' => 'decimal:2', 'received_at' => 'datetime', 'delivered_at' => 'datetime'];
    }

    public function departure(): BelongsTo
    {
        return $this->belongsTo(RouteDeparture::class, 'route_departure_id');
    }

    public function airDeparture(): BelongsTo
    {
        return $this->belongsTo(AirDeparture::class, 'air_departure_id');
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function reservationSeat(): BelongsTo
    {
        return $this->belongsTo(ReservationSeat::class);
    }
}
