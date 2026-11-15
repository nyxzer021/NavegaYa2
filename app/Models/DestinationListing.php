<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DestinationListing extends Model
{
    protected $fillable = ['organization_id', 'owner_user_id', 'destination_city_id', 'business_category_id', 'type', 'status', 'name', 'category', 'description', 'services', 'address', 'contact_phone', 'website_url', 'price_reference', 'opening_hours', 'opening_schedule', 'banner_path', 'is_featured', 'sort_order', 'is_active', 'moderation_notes', 'views', 'contact_clicks'];

    protected function casts(): array
    {
        return ['opening_schedule' => 'array', 'is_featured' => 'boolean', 'is_active' => 'boolean'];
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(DestinationCity::class, 'destination_city_id');
    }

    public function businessCategory(): BelongsTo
    {
        return $this->belongsTo(BusinessCategory::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(DestinationListingImage::class)->orderBy('sort_order');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(DestinationListingOffer::class)->orderBy('sort_order');
    }

    /**
     * Fichas aprobadas cuyo negocio mantiene su plan vigente. Las fichas creadas por el superadmin no dependen de un plan.
     */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('status', 'published')
            ->where(fn (Builder $builder) => $builder->whereNull('organization_id')
                ->orWhereHas('organization', fn (Builder $organization) => $organization->withPublicPlan()));
    }

    public function isPubliclyVisible(): bool
    {
        return static::query()->publiclyVisible()->whereKey($this->getKey())->exists();
    }
}
