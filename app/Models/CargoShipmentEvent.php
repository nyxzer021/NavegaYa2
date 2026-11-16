<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CargoShipmentEvent extends Model
{
    protected $fillable = ['cargo_shipment_id', 'kind', 'user_id', 'happened_at', 'note'];

    protected function casts(): array
    {
        return ['happened_at' => 'datetime'];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(CargoShipment::class, 'cargo_shipment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
