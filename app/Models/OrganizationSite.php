<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationSite extends Model
{
    protected $fillable = [
        'organization_id', 'name', 'kind', 'address', 'city', 'hours', 'receives_cargo',
        'show_on_where_to_buy', 'is_active', 'hidden_by_admin', 'hidden_reason', 'hidden_by', 'hidden_at',
    ];

    protected function casts(): array
    {
        return [
            'receives_cargo' => 'boolean',
            'show_on_where_to_buy' => 'boolean',
            'is_active' => 'boolean',
            'hidden_by_admin' => 'boolean',
            'hidden_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function isPubliclyListed(): bool
    {
        return $this->is_active && $this->show_on_where_to_buy && ! $this->hidden_by_admin;
    }
}
