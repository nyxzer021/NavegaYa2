<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Organization extends Model
{
    protected static function booted(): void
    {
        static::created(fn (self $model) => $model->internal_code ?: $model->forceFill(['internal_code' => 'EMP-'.str_pad((string) $model->id, 6, '0', STR_PAD_LEFT)])->saveQuietly());
    }

    protected $fillable = [
        'type', 'modality', 'base_city', 'legal_name', 'commercial_name', 'contact_name', 'ruc', 'email', 'phone', 'whatsapp',
        'address', 'website', 'status', 'is_marketplace_paused', 'commission_rate', 'commercial_plan', 'agreement_number',
        'commission_starts_on', 'commission_ends_on', 'commission_notes', 'physical_sales_commission_rate',
        'verified_at', 'contact_verified_at', 'public_description', 'logo_path', 'cover_image_path',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'contact_verified_at' => 'datetime',
            'is_marketplace_paused' => 'boolean',
            'commission_rate' => 'decimal:2',
            'commission_starts_on' => 'date',
            'commission_ends_on' => 'date',
            'physical_sales_commission_rate' => 'decimal:2',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('status')->withTimestamps();
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(OrganizationDocument::class);
    }

    public function transportRoutes(): HasMany
    {
        return $this->hasMany(TransportRoute::class);
    }

    public function vessels(): HasMany
    {
        return $this->hasMany(Vessel::class);
    }

    public function aircraft(): HasMany
    {
        return $this->hasMany(Aircraft::class);
    }

    public function airRoutes(): HasMany
    {
        return $this->hasMany(AirRoute::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(CompanyReview::class);
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(Settlement::class);
    }

    public function routeDepartures(): HasManyThrough
    {
        return $this->hasManyThrough(RouteDeparture::class, TransportRoute::class);
    }

    public function airDepartures(): HasManyThrough
    {
        return $this->hasManyThrough(AirDeparture::class, AirRoute::class);
    }
}
