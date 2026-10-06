<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AirRoute extends Model
{
    protected $fillable = ['organization_id', 'master_route_id', 'origin_city', 'destination_city', 'code', 'estimated_duration_minutes', 'status', 'description'];

    protected static function booted(): void
    {
        static::saving(function (self $route): void {
            if (mb_strtolower(trim($route->origin_city)) === mb_strtolower(trim($route->destination_city))) {
                throw ValidationException::withMessages([
                    'destination_city' => 'La ciudad de destino debe ser diferente a la ciudad de origen.',
                ]);
            }
        });

        static::creating(fn (self $m) => $m->code ?: $m->forceFill(['code' => 'AIR-'.Str::upper(Str::random(8))]));
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function masterRoute(): BelongsTo
    {
        return $this->belongsTo(MasterRoute::class);
    }

    public function departures(): HasMany
    {
        return $this->hasMany(AirDeparture::class);
    }
}
