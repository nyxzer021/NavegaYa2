<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgencyLotRelease extends Model
{
    protected $fillable = ['agency_lot_id', 'vessel_seat_id', 'note', 'released_by_user_id'];

    public function lot(): BelongsTo
    {
        return $this->belongsTo(AgencyLot::class, 'agency_lot_id');
    }

    public function seat(): BelongsTo
    {
        return $this->belongsTo(VesselSeat::class, 'vessel_seat_id');
    }
}
