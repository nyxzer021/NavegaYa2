<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RouteDeparture extends Model
{
    protected static function booted(): void
    {
        static::created(function (self $model): void {
            if (! $model->code) {
                $date = ($model->departure_at ?? now())->format('ymd');
                $model->forceFill(['code' => 'SAL-'.$date.'-'.str_pad((string) $model->id, 6, '0', STR_PAD_LEFT)])->saveQuietly();
            }
        });
    }

    protected $fillable = [
        'transport_route_id', 'vessel_id', 'departure_at', 'boarding_starts_at',
        'cargo_reception_starts_at', 'estimated_arrival_at', 'fare', 'cargo_enabled',
        'included_baggage_kg', 'cargo_payment_location', 'status', 'is_published', 'notes', 'cargo_notes',
    ];

    protected function casts(): array
    {
        return [
            'departure_at' => 'datetime',
            'boarding_starts_at' => 'datetime',
            'cargo_reception_starts_at' => 'datetime',
            'estimated_arrival_at' => 'datetime',
            'fare' => 'decimal:2',
            'cargo_enabled' => 'boolean',
            'included_baggage_kg' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    public function transportRoute(): BelongsTo
    {
        return $this->belongsTo(TransportRoute::class);
    }

    public function vessel(): BelongsTo
    {
        return $this->belongsTo(Vessel::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }
}
