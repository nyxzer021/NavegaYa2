<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'reservation_id', 'checkout_order_id', 'method', 'provider', 'provider_reference', 'webhook_event_id',
        'amount', 'currency_code', 'commission_rate', 'commission_amount', 'operator_net', 'status', 'paid_at',
        'commission_status', 'commission_invoiced_at', 'commission_paid_at', 'commission_reference', 'provider_payload',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'operator_net' => 'decimal:2',
            'paid_at' => 'datetime',
            'commission_invoiced_at' => 'datetime',
            'commission_paid_at' => 'datetime',
            'provider_payload' => 'array',
        ];
    }

    public function checkoutOrder(): BelongsTo
    {
        return $this->belongsTo(CheckoutOrder::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }
}
