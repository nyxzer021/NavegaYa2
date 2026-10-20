<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DestinationListingOffer extends Model
{
    protected $fillable = ['destination_listing_id', 'title', 'offer_type', 'description', 'price', 'price_unit', 'capacity', 'image_path', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'capacity' => 'integer', 'is_active' => 'boolean'];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(DestinationListing::class, 'destination_listing_id');
    }
}
