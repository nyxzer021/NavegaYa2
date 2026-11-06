<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganizationPhoto extends Model
{
    protected $fillable = [
        'organization_id', 'album_id', 'company_review_id', 'path', 'caption', 'status', 'alt_text', 'sort_order',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function album(): BelongsTo
    {
        return $this->belongsTo(OrganizationAlbum::class, 'album_id');
    }

    public function likes(): HasMany
    {
        return $this->hasMany(PhotoLike::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(PhotoComment::class);
    }
}
