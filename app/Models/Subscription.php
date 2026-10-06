<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $fillable = [
        'organization_id', 'subscription_plan_id', 'status', 'monthly_price', 'currency',
        'starts_on', 'ends_on', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return ['monthly_price' => 'decimal:2', 'starts_on' => 'date', 'ends_on' => 'date', 'cancelled_at' => 'datetime'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }
}
