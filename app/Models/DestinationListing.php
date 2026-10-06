<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DestinationListing extends Model
{
    protected $fillable = ['destination_city_id', 'type', 'name', 'category', 'description', 'address', 'contact_phone', 'website_url', 'price_reference', 'opening_hours', 'is_featured', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_featured' => 'boolean', 'is_active' => 'boolean'];
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(DestinationCity::class, 'destination_city_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(DestinationListingImage::class)->orderBy('sort_order');
    }
}
