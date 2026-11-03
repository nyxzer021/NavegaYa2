<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationPhoto extends Model
{
    protected $fillable = ['organization_id', 'path', 'caption', 'sort_order'];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
