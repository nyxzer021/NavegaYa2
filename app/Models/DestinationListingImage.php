<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DestinationListingImage extends Model
{
    protected $fillable = ['destination_listing_id', 'path', 'sort_order', 'is_cover'];

    protected function casts(): array
    {
        return ['is_cover' => 'boolean'];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(DestinationListing::class, 'destination_listing_id');
    }
}
