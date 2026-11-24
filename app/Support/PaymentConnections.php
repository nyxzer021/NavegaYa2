<?php

namespace App\Support;

use App\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class PaymentConnections
{
    public const CULQI_RECEIVED = 'culqi.webhook_received_at';

    public const MERCADOPAGO_RECEIVED = 'mercadopago.webhook_received_at';

    public static function rememberCulqi(): void
    {
        Cache::forever(self::CULQI_RECEIVED, now()->toIso8601String());
    }

    public static function lastCulqiWebhook(): ?Carbon
    {
        $cached = self::moment(Cache::get(self::CULQI_RECEIVED));
        $stored = self::moment(Payment::query()
            ->where('provider', 'culqi')
            ->whereNotNull('webhook_event_id')
            ->max('updated_at'));
        if ($cached && $stored) {
            return $cached->greaterThan($stored) ? $cached : $stored;
        }

        return $cached ?? $stored;
    }

    public static function rememberMercadoPago(): void
    {
        Cache::forever(self::MERCADOPAGO_RECEIVED, now()->toIso8601String());
    }

    public static function lastMercadoPagoWebhook(): ?Carbon
    {
        $cached = self::moment(Cache::get(self::MERCADOPAGO_RECEIVED));
        $stored = self::moment(Payment::query()
            ->where('provider', 'mercadopago')
            ->whereNotNull('webhook_event_id')
            ->max('updated_at'));
        if ($cached && $stored) {
            return $cached->greaterThan($stored) ? $cached : $stored;
        }

        return $cached ?? $stored;
    }

    public static function tail(?string $value): ?string
    {
        $value = preg_replace('/\s+/', '', (string) $value) ?? '';
        if (strlen($value) < 8) {
            return null;
        }

        return substr($value, -4);
    }

    private static function moment(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return Carbon::parse($value);
    }
}
