<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationDocument extends Model
{
    protected $fillable = ['organization_id', 'type', 'path', 'original_name', 'expires_on', 'reference_number', 'status', 'reviewed_at'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime', 'expires_on' => 'date'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
