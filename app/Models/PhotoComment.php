<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PhotoComment extends Model
{
    protected $fillable = ['organization_photo_id', 'user_id', 'parent_id', 'body', 'status'];

    public function photo(): BelongsTo
    {
        return $this->belongsTo(OrganizationPhoto::class, 'organization_photo_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
