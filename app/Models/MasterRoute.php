<?php

namespace App\Models;

use Database\Factories\MasterRouteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MasterRoute extends Model
{
    /** @use HasFactory<MasterRouteFactory> */
    use HasFactory;

    protected $fillable = [
        'code', 'modality', 'origin_city', 'destination_city', 'origin_port_id',
        'destination_port_id', 'river_basin', 'corridor', 'estimated_duration_text', 'path_geojson', 'status',
    ];

    protected function casts(): array
    {
        return [
            'path_geojson' => 'array',
        ];
    }

    public function originPort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'origin_port_id');
    }

    public function destinationPort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'destination_port_id');
    }

    public function transportRoutes(): HasMany
    {
        return $this->hasMany(TransportRoute::class);
    }

    public function airRoutes(): HasMany
    {
        return $this->hasMany(AirRoute::class);
    }
}
