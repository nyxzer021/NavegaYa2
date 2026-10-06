<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DestinationReview extends Model
{
    protected $fillable = ['destination_city_id', 'user_id', 'rating', 'comment', 'status'];

    public function city(): BelongsTo
    {
        return $this->belongsTo(DestinationCity::class, 'destination_city_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
