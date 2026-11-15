<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    protected $fillable = [
        'organization_id', 'subscription_plan_id', 'scheduled_plan_id', 'status', 'monthly_price', 'currency',
        'starts_on', 'ends_on', 'scheduled_plan_starts_on', 'benefits_snapshot', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return ['monthly_price' => 'decimal:2', 'starts_on' => 'date', 'ends_on' => 'date', 'scheduled_plan_starts_on' => 'date', 'benefits_snapshot' => 'array', 'cancelled_at' => 'datetime'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function scheduledPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'scheduled_plan_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Suscripción pagada y dentro de su vigencia (hora de Lima).
     */
    public function scopeCurrent(Builder $query): Builder
    {
        $today = now('America/Lima')->toDateString();

        return $query->where('status', 'active')
            ->where(fn (Builder $builder) => $builder->whereNull('ends_on')->orWhereDate('ends_on', '>=', $today));
    }
}
