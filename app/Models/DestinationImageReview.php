<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DestinationImageReview extends Model
{
    protected $fillable = ['destination_listing_image_id', 'user_id', 'rating', 'comment', 'status'];

    public function image(): BelongsTo
    {
        return $this->belongsTo(DestinationListingImage::class, 'destination_listing_image_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
