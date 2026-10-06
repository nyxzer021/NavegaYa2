<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Aircraft extends Model
{
    protected $fillable = ['organization_id', 'name', 'registration_number', 'model', 'manufacturer', 'dgac_certificate_number', 'airworthiness_certificate_number', 'airworthiness_expires_at', 'aviation_policy', 'aviation_policy_expires_at', 'emergency_equipment', 'seat_capacity', 'status', 'description', 'cover_image_path'];

    protected function casts(): array
    {
        return ['seat_capacity' => 'integer', 'airworthiness_expires_at' => 'date', 'aviation_policy_expires_at' => 'date'];
    }

    public function seats(): HasMany
    {
        return $this->hasMany(AircraftSeat::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
