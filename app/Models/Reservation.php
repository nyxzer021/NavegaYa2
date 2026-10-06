<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reservation extends Model
{
    protected $fillable = ['user_id', 'checkout_order_id', 'route_departure_id', 'air_departure_id', 'code', 'contact_name', 'contact_email', 'contact_phone', 'contact_document', 'total_amount', 'status', 'sales_channel', 'payment_method', 'expires_at', 'paid_at'];

    protected function casts(): array
    {
        return ['total_amount' => 'decimal:2', 'expires_at' => 'datetime', 'paid_at' => 'datetime'];
    }

    public function checkoutOrder(): BelongsTo
    {
        return $this->belongsTo(CheckoutOrder::class);
    }

    public function departure(): BelongsTo
    {
        return $this->belongsTo(RouteDeparture::class, 'route_departure_id');
    }

    public function airDeparture(): BelongsTo
    {
        return $this->belongsTo(AirDeparture::class, 'air_departure_id');
    }

    public function isAir(): bool
    {
        return $this->air_departure_id !== null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function seats(): HasMany
    {
        return $this->hasMany(ReservationSeat::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
