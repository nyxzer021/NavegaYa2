<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouteFare extends Model
{
    public const SPACES = ['seat', 'hammock', 'cabin_bed'];

    public const PASSENGERS = ['adult', 'child', 'student', 'senior', 'infant'];

    protected $fillable = [
        'organization_id', 'transport_route_id', 'air_route_id', 'space_type', 'passenger_type',
        'amount', 'includes_meal', 'starts_on', 'ends_on',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'includes_meal' => 'boolean',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
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
