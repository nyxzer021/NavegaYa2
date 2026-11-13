<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DepartureIncident extends Model
{
    public const KINDS = ['delay', 'breakdown', 'weather', 'river_level', 'other'];

    public const SEVERITIES = ['low', 'medium', 'high'];

    public const ACTIONS = ['record', 'reschedule', 'cancel'];

    protected $fillable = [
        'organization_id', 'route_departure_id', 'air_departure_id', 'reported_by_user_id',
        'kind', 'severity', 'description', 'action',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function routeDeparture(): BelongsTo
    {
        return $this->belongsTo(RouteDeparture::class);
    }

    public function airDeparture(): BelongsTo
    {
        return $this->belongsTo(AirDeparture::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(IncidentPhoto::class);
    }
}
