<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationBanner extends Model
{
    protected $fillable = ['organization_id', 'path', 'focal_x', 'focal_y', 'position'];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
