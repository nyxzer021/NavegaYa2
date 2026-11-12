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
        'destination_port_id', 'river_basin', 'corridor', 'estimated_duration_text', 'estimated_duration_minutes', 'duration_needs_review', 'path_geojson', 'status',
        'requested_organization_id', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'path_geojson' => 'array',
            'estimated_duration_minutes' => 'integer',
            'duration_needs_review' => 'boolean',
        ];
    }

    public static function formatDuration(?int $minutes): string
    {
        if (! $minutes) {
            return __('public.duration_review');
        }
        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        return $hours > 0 ? trim($hours.' h '.($remaining ? $remaining.' min' : '')) : $remaining.' min';
    }

    public function getFormattedDurationAttribute(): string
    {
        return $this->duration_needs_review ? __('public.duration_review') : self::formatDuration($this->estimated_duration_minutes);
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

    public function requestedOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'requested_organization_id');
    }
}
