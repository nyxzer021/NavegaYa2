<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketRefund extends Model
{
    protected $fillable = [
        'organization_id', 'reservation_id', 'payment_id', 'source', 'cause', 'status',
        'fare_amount', 'fee_amount', 'amount', 'policy_percent', 'explanation', 'reason',
        'rejection_reason', 'operation_number', 'web_fare_refund_id', 'cash_payment_id',
        'requested_by_user_id', 'decided_by_user_id', 'decided_at', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'fare_amount' => 'decimal:2',
            'fee_amount' => 'decimal:2',
            'amount' => 'decimal:2',
            'policy_percent' => 'decimal:2',
            'decided_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function webFareRefund(): BelongsTo
    {
        return $this->belongsTo(WebFareRefund::class);
    }

    public function cashPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'cash_payment_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }
}
