<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPaymentRequest extends Model
{
    public const METHODS = ['transfer' => 'Transferencia bancaria', 'yape' => 'Yape', 'plin' => 'Plin'];

    protected $fillable = [
        'organization_id', 'subscription_id', 'subscription_plan_id', 'months', 'amount', 'method',
        'operation_number', 'paid_on', 'voucher_path', 'status', 'rejection_reason',
        'requested_by_user_id', 'reviewed_by_user_id', 'reviewed_at', 'payment_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_on' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function methodLabel(): string
    {
        return self::METHODS[$this->method] ?? $this->method;
    }
}
