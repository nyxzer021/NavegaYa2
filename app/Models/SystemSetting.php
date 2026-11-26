<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Once;

class SystemSetting extends Model
{
    protected $fillable = ['key', 'value'];

    protected static function booted(): void
    {
        static::saved(fn () => Once::flush());
        static::deleted(fn () => Once::flush());
    }

    /**
     * Lee todos los ajustes una sola vez por petición (o por trabajo de la cola) en lugar de una consulta por ajuste.
     */
    public static function value(string $key, ?string $default = null): ?string
    {
        $settings = once(fn (): array => static::query()->pluck('value', 'key')->all());

        return $settings[$key] ?? $default;
    }
}
