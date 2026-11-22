<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgencyLot extends Model
{
    protected $fillable = ['agency_id', 'route_departure_id', 'unit_price', 'payment_status', 'note'];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function departure(): BelongsTo
    {
        return $this->belongsTo(RouteDeparture::class, 'route_departure_id');
    }

    public function holds(): HasMany
    {
        return $this->hasMany(RouteDepartureSeatHold::class);
    }

    public function releases(): HasMany
    {
        return $this->hasMany(AgencyLotRelease::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(AgencyLotPayment::class);
    }
}
