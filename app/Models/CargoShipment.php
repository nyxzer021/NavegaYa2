<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CargoShipment extends Model
{
    protected $fillable = ['organization_id', 'route_departure_id', 'air_departure_id', 'reservation_id', 'reservation_seat_id', 'code', 'sender_name', 'sender_document', 'sender_phone', 'recipient_name', 'recipient_document', 'recipient_phone', 'description', 'package_count', 'declared_weight_kg', 'verified_weight_kg', 'length_cm', 'width_cm', 'height_cm', 'fragile', 'rate_per_kg', 'amount', 'payment_method', 'collected_by_user_id', 'organization_site_id', 'payment_status', 'status', 'receipt_number', 'received_at', 'received_by_user_id', 'boarded_by_user_id', 'boarded_at', 'delivered_at', 'delivered_by_user_id', 'collector_name', 'collector_document', 'needs_reassignment'];

    protected function casts(): array
    {
        return [
            'declared_weight_kg' => 'decimal:2',
            'verified_weight_kg' => 'decimal:2',
            'rate_per_kg' => 'decimal:2',
            'amount' => 'decimal:2',
            'fragile' => 'boolean',
            'needs_reassignment' => 'boolean',
            'received_at' => 'datetime',
            'boarded_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(OrganizationSite::class, 'organization_site_id');
    }

    public function departure(): BelongsTo
    {
        return $this->belongsTo(RouteDeparture::class, 'route_departure_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(CargoShipmentEvent::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
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
