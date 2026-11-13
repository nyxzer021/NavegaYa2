<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Discount extends Model
{
    public const CHANNELS = ['web', 'counter', 'agency'];

    protected $fillable = [
        'organization_id', 'name', 'kind', 'value', 'channels', 'requires_document',
        'usage_limit', 'redeemed_count', 'starts_on', 'ends_on', 'status',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'channels' => 'array',
            'requires_document' => 'boolean',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DiscountRoute::class);
    }
}
