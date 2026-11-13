<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrewMember extends Model
{
    public const ROLES = ['captain', 'engineer', 'sailor', 'pilot', 'copilot'];

    protected $fillable = [
        'organization_id', 'name', 'document_number', 'phone', 'role',
        'license_number', 'license_expires_on', 'status',
    ];

    protected function casts(): array
    {
        return ['license_expires_on' => 'date'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DepartureAssignment::class);
    }
}
