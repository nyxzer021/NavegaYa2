<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TransportRoute extends Model
{
    protected $fillable = ['organization_id', 'master_route_id', 'origin_port_id', 'destination_port_id', 'code', 'estimated_duration_minutes', 'distance_km', 'status', 'description'];

    protected function casts(): array
    {
        return ['estimated_duration_minutes' => 'integer', 'distance_km' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $route): void {
            if ((int) $route->origin_port_id === (int) $route->destination_port_id) {
                throw ValidationException::withMessages([
                    'destination_port_id' => 'El puerto de destino debe ser diferente al puerto de origen.',
                ]);
            }

            $originCity = Port::query()->whereKey($route->origin_port_id)->value('city');
            $destinationCity = Port::query()->whereKey($route->destination_port_id)->value('city');
            if ($originCity && $destinationCity && mb_strtolower(trim($originCity)) === mb_strtolower(trim($destinationCity))) {
                throw ValidationException::withMessages([
                    'destination_port_id' => 'La ciudad de destino debe ser diferente a la ciudad de origen.',
                ]);
            }
        });

        static::creating(function (self $route) {
            if (! $route->code) {
                $route->code = 'TMP-'.Str::upper(Str::random(10));
            }
        });
        static::created(function (self $route) {
            $origin = Port::find($route->origin_port_id)?->city ?? 'ORG';
            $destination = Port::find($route->destination_port_id)?->city ?? 'DST';
            $route->forceFill(['code' => 'RUT-'.self::cityCode($origin).'-'.self::cityCode($destination).'-'.str_pad((string) $route->id, 4, '0', STR_PAD_LEFT)])->saveQuietly();
        });
    }

    private static function cityCode(string $city): string
    {
        $map = ['Iquitos' => 'IQT', 'Yurimaguas' => 'YUR', 'Nauta' => 'NAU', 'Contamana' => 'CON', 'Requena' => 'REQ', 'Pucallpa' => 'PUC', 'Puerto Maldonado' => 'PMD', 'Tarapoto' => 'TAR'];

        return $map[$city] ?? strtoupper(substr(preg_replace('/[^A-Za-z]/', '', Str::ascii($city)), 0, 3));
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function masterRoute(): BelongsTo
    {
        return $this->belongsTo(MasterRoute::class);
    }

    public function originPort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'origin_port_id');
    }

    public function destinationPort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'destination_port_id');
    }

    public function departures(): HasMany
    {
        return $this->hasMany(RouteDeparture::class);
    }
}
