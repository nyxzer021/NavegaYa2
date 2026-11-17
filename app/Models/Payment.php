<?php

namespace App\Models;

use App\Support\DiscountLedger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class Payment extends Model
{
    protected $fillable = [
        'reservation_id', 'cargo_shipment_id', 'collected_by_user_id', 'organization_site_id', 'cash_session_id', 'checkout_order_id', 'subscription_id', 'purpose', 'method', 'provider', 'provider_reference', 'webhook_event_id',
        'amount', 'cash_tendered', 'cash_change', 'operation_number', 'adjustment_note', 'currency_code', 'commission_rate', 'commission_amount', 'operator_net', 'status', 'paid_at', 'voided_at',
        'requires_review', 'review_reason', 'commission_status', 'commission_invoiced_at', 'commission_paid_at', 'commission_reference', 'provider_payload',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'cash_tendered' => 'decimal:2',
            'cash_change' => 'decimal:2',
            'voided_at' => 'datetime',
            'commission_rate' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'operator_net' => 'decimal:2',
            'paid_at' => 'datetime',
            'requires_review' => 'boolean',
            'commission_invoiced_at' => 'datetime',
            'commission_paid_at' => 'datetime',
            'provider_payload' => 'array',
        ];
    }

    public function checkoutOrder(): BelongsTo
    {
        return $this->belongsTo(CheckoutOrder::class);
    }

    protected static function booted(): void
    {
        static::updating(function (self $payment): void {
            $payment->guardClosedSession($payment->getOriginal('cash_session_id'));
        });
        static::deleting(function (self $payment): void {
            $payment->guardClosedSession($payment->cash_session_id);
        });
        static::updated(function (self $payment): void {
            if ($payment->wasChanged('status') && $payment->status === 'refunded' && $payment->reservation_id) {
                $reservation = $payment->reservation;
                if ($reservation !== null) {
                    DiscountLedger::release($reservation);
                }
            }
        });
    }

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    private function guardClosedSession(mixed $sessionId): void
    {
        if (! $sessionId) {
            return;
        }
        $closed = CashSession::query()->whereKey($sessionId)->whereNotNull('closed_at')->exists();
        if ($closed) {
            throw ValidationException::withMessages([
                'payment' => 'Ese cobro pertenece a un turno cerrado y no se puede modificar.',
            ]);
        }
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function cargoShipment(): BelongsTo
    {
        return $this->belongsTo(CargoShipment::class);
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by_user_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(OrganizationSite::class, 'organization_site_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
