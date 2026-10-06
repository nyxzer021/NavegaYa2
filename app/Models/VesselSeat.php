<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VesselSeat extends Model
{
    protected $fillable = ['vessel_id', 'code', 'deck', 'seat_class', 'row_position', 'column_position', 'is_available'];

    protected function casts(): array
    {
        return ['row_position' => 'integer', 'column_position' => 'integer', 'is_available' => 'boolean'];
    }

    public function vessel(): BelongsTo
    {
        return $this->belongsTo(Vessel::class);
    }

    public function reservationSeats(): HasMany
    {
        return $this->hasMany(ReservationSeat::class);
    }
}
