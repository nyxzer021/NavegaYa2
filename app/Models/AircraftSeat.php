<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AircraftSeat extends Model
{
    protected $fillable = ['aircraft_id', 'cabin', 'code', 'row_position', 'column_position', 'is_available'];

    protected function casts(): array
    {
        return ['is_available' => 'boolean'];
    }

    public function aircraft(): BelongsTo
    {
        return $this->belongsTo(Aircraft::class);
    }
}
