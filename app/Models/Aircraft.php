<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Aircraft extends Model
{
    protected $fillable = ['organization_id', 'name', 'registration_number', 'model', 'manufacturer', 'aircraft_kind', 'dgac_certificate_number', 'airworthiness_certificate_number', 'airworthiness_expires_at', 'aviation_policy', 'aviation_policy_expires_at', 'emergency_equipment', 'seat_capacity', 'max_takeoff_weight_kg', 'useful_load_kg', 'max_baggage_kg_per_passenger', 'status', 'review_notes', 'submitted_for_review_at', 'reviewed_at', 'reviewed_by', 'suspension_reason', 'suspended_at', 'suspended_by', 'description', 'cover_image_path'];

    protected function casts(): array
    {
        return [
            'seat_capacity' => 'integer',
            'airworthiness_expires_at' => 'date',
            'aviation_policy_expires_at' => 'date',
            'max_takeoff_weight_kg' => 'decimal:2',
            'useful_load_kg' => 'decimal:2',
            'max_baggage_kg_per_passenger' => 'decimal:2',
            'submitted_for_review_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    public function seats(): HasMany
    {
        return $this->hasMany(AircraftSeat::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function airDepartures(): HasMany
    {
        return $this->hasMany(AirDeparture::class);
    }

    public function maintenancePeriods(): HasMany
    {
        return $this->hasMany(AircraftMaintenancePeriod::class);
    }
}
