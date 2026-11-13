<?php

namespace App\Models;

use App\Support\DiscountLedger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Reservation extends Model
{
    protected $fillable = ['user_id', 'checkout_order_id', 'route_departure_id', 'air_departure_id', 'code', 'contact_name', 'contact_email', 'contact_phone', 'contact_document', 'total_amount', 'base_amount', 'discount_amount', 'service_fee_amount', 'status', 'sales_channel', 'payment_method', 'expires_at', 'paid_at',
        'travel_policy_version_id', 'platform_terms_version_id', 'discount_id', 'discount_held',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'base_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'service_fee_amount' => 'decimal:2',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'discount_held' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::updated(function (self $reservation): void {
            if ($reservation->wasChanged('status') && in_array($reservation->status, ['cancelled', 'refunded', 'expired'], true)) {
                DiscountLedger::release($reservation);
            }
        });
    }

    /**
     * @param  iterable<int, int|string>  $ids
     */
    public static function expirePending(iterable $ids): void
    {
        static::query()->whereIn('id', collect($ids)->all())->get()->each(function (self $reservation): void {
            $reservation->seats()->delete();
            if ($reservation->status !== 'expired') {
                $reservation->update(['status' => 'expired']);
            }
        });
    }

    public function checkoutOrder(): BelongsTo
    {
        return $this->belongsTo(CheckoutOrder::class);
    }

    public function departure(): BelongsTo
    {
        return $this->belongsTo(RouteDeparture::class, 'route_departure_id');
    }

    public function airDeparture(): BelongsTo
    {
        return $this->belongsTo(AirDeparture::class, 'air_departure_id');
    }

    public function isAir(): bool
    {
        return $this->air_departure_id !== null;
    }

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function seats(): HasMany
    {
        return $this->hasMany(ReservationSeat::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function companyReview(): HasOne
    {
        return $this->hasOne(CompanyReview::class);
    }
}
