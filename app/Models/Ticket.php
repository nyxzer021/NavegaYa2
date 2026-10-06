<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
    protected $fillable = ['reservation_seat_id', 'code', 'boarding_token', 'status', 'issued_at', 'checked_in_at', 'boarded_at', 'boarded_by_user_id', 'boarded_port_id'];

    protected function casts(): array
    {
        return ['issued_at' => 'datetime', 'checked_in_at' => 'datetime', 'boarded_at' => 'datetime'];
    }

    public function reservationSeat(): BelongsTo
    {
        return $this->belongsTo(ReservationSeat::class);
    }

    public function boardedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'boarded_by_user_id');
    }

    public function boardedPort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'boarded_port_id');
    }
}
