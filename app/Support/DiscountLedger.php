<?php

namespace App\Support;

use App\Models\Discount;
use App\Models\Reservation;
use DomainException;
use Illuminate\Support\Facades\DB;

class DiscountLedger
{
    public static function redeem(Discount $discount): void
    {
        $locked = Discount::query()->whereKey($discount->id)->lockForUpdate()->firstOrFail();
        if ($locked->usage_limit !== null && $locked->redeemed_count >= $locked->usage_limit) {
            throw new DomainException('Ese descuento ya no tiene usos disponibles.');
        }
        $locked->increment('redeemed_count');
    }

    public static function release(Reservation $reservation): void
    {
        if (! $reservation->discount_id || ! $reservation->discount_held) {
            return;
        }

        DB::transaction(function () use ($reservation): void {
            $locked = Reservation::query()->whereKey($reservation->id)->lockForUpdate()->first();
            if ($locked === null || ! $locked->discount_held || ! $locked->discount_id) {
                return;
            }
            $discount = Discount::query()->whereKey($locked->discount_id)->lockForUpdate()->first();
            if ($discount !== null && $discount->redeemed_count > 0) {
                $discount->decrement('redeemed_count');
            }
            $locked->forceFill(['discount_held' => false])->saveQuietly();
        });
    }
}
