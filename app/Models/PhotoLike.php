<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhotoLike extends Model
{
    protected $fillable = ['organization_photo_id', 'user_id'];

    public function photo(): BelongsTo
    {
        return $this->belongsTo(OrganizationPhoto::class, 'organization_photo_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
