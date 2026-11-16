<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agency extends Model
{
    protected $fillable = [
        'organization_id', 'name', 'ruc', 'contact_name', 'phone', 'status',
        'max_surcharge_percent', 'suspension_reason', 'suspended_at', 'suspended_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'max_surcharge_percent' => 'decimal:2',
            'suspended_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function lots(): HasMany
    {
        return $this->hasMany(AgencyLot::class);
    }

    public function sellers(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(AgencyInvitation::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function suspendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suspended_by_user_id');
    }

    /**
     * Una suspensión de NavegaYA solo la levanta NavegaYA, no la empresa.
     */
    public function isSuspendedByPlatform(): bool
    {
        return $this->status === 'suspended'
            && $this->suspendedBy?->roles()->where('code', 'super_admin')->exists() === true;
    }
}
