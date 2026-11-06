<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganizationAlbum extends Model
{
    protected $fillable = ['organization_id', 'title', 'position'];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(OrganizationPhoto::class, 'album_id');
    }
}
