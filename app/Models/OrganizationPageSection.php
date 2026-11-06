<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationPageSection extends Model
{
    protected $fillable = ['organization_id', 'key', 'label', 'position', 'is_enabled'];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function isMandatory(): bool
    {
        return in_array($this->key, ['salidas', 'informacion'], true);
    }
}
