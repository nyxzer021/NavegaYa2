<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Reservation;
use App\Services\BookingPaymentService;
use App\Services\CulqiException;
use App\Services\CulqiGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class CulqiPaymentController extends Controller
{
    public function order(Request $request, string $code, CulqiGateway $culqi): JsonResponse
    {
        abort_unless($culqi->ready(), 503, __('payments.culqi_off'));
        $reservation = Reservation::where('code', $code)->firstOrFail();
        $this->authorizeReservation($request, $reservation);
        if (! $this->payable($reservation)) {
            return response()->json(['message' => __('payments.not_payable')], 422);
        }
        $cents = $culqi->ticketCents($reservation);
        $names = preg_split('/\s+/', trim((string) $reservation->contact_name), 2) ?: [];
        try {
            $order = $culqi->createOrder([
                'amount' => $cents,
                'currency_code' => 'PEN',
                'description' => 'Pasajes NavegaYA '.$reservation->code,
                'order_number' => $reservation->code,
                'client_details' => [
                    'first_name' => $names[0] ?? $reservation->contact_name,
                    'last_name' => $names[1] ?? '-',
                    'email' => $reservation->contact_email,
                    'phone_number' => preg_replace('/\D+/', '', (string) $reservation->contact_phone),
                ],
                'expiration_date' => $reservation->expires_at?->timestamp,
                'confirm' => true,
                'metadata' => ['reservation_code' => $reservation->code],
            ]);
        } catch (CulqiException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
        if (($order['object'] ?? null) !== 'order' || blank($order['id'] ?? null) || (int) ($order['amount'] ?? 0) !== $cents) {
            return response()->json(['message' => __('payments.order_failed')], 422);
        }
        Payment::query()->updateOrCreate(
            ['provider_reference' => $order['id']],
            [
                'reservation_id' => $reservation->id,
                'method' => 'pagoefectivo',
                'provider' => 'culqi',
                'amount' => number_format($cents / 100, 2, '.', ''),
                'currency_code' => 'PEN',
                'status' => 'pending',
                'provider_payload' => $culqi->safePayload($order),
            ],
        );

        return response()->json([
            'order_id' => $order['id'],
            'payment_code' => $order['payment_code'] ?? null,
            'expires_at' => $reservation->expires_at?->toIso8601String(),
        ]);
    }

    public function charge(Request $request, string $code, BookingPaymentService $payments, CulqiGateway $culqi): JsonResponse
    {
        abort_unless($culqi->ready(), 503, __('payments.culqi_off'));
        $data = $request->validate([
            'source_id' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9_]+$/'],
            'amount' => ['sometimes', 'integer'],
        ]);
        $reservation = Reservation::with('seats')->where('code', $code)->firstOrFail();
        $this->authorizeReservation($request, $reservation);
        if (! $this->payable($reservation)) {
            return response()->json(['message' => __('payments.not_payable')], 422);
        }
        $cents = $culqi->ticketCents($reservation);
        if (array_key_exists('amount', $data) && (int) $data['amount'] !== $cents) {
            return response()->json(['message' => __('payments.amount_rejected')], 422);
        }
        $names = preg_split('/\s+/', trim((string) $reservation->contact_name), 2) ?: [];
        try {
            $charge = $culqi->createCharge([
                'amount' => $cents,
                'currency_code' => 'PEN',
                'email' => $reservation->contact_email,
                'source_id' => $data['source_id'],
                'capture' => true,
                'description' => 'Pasajes NavegaYA '.$reservation->code,
                'metadata' => ['reservation_code' => $reservation->code],
                'antifraud_details' => [
                    'country_code' => 'PE',
                    'first_name' => $names[0] ?? $reservation->contact_name,
                    'last_name' => $names[1] ?? '-',
                    'email' => $reservation->contact_email,
                    'phone_number' => preg_replace('/\D+/', '', (string) $reservation->contact_phone),
                ],
            ]);
        } catch (CulqiException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
        $chargeId = (string) ($charge['id'] ?? '');
        $charged = (int) ($charge['amount'] ?? 0);
        if (($charge['object'] ?? null) !== 'charge' || ($charge['state'] ?? null) !== 'Exitosa' || $chargeId === '') {
            return response()->json(['message' => __('payments.culqi_rejected')], 422);
        }
        if ($charged !== $cents) {
            $culqi->releaseCharge($reservation, $chargeId, $charged, 'solicitud_comprador');

            return response()->json(['message' => __('payments.amount_rejected')], 422);
        }

        return $this->settle($reservation, $charge, $data['source_id'], $payments, $culqi);
    }

    /**
     * @param  array<string, mixed>  $charge
     */
    private function settle(Reservation $reservation, array $charge, string $sourceId, BookingPaymentService $payments, CulqiGateway $culqi): JsonResponse
    {
        $chargeId = (string) $charge['id'];
        $cents = (int) $charge['amount'];
        $wasConfirmed = $reservation->status === 'confirmed';
        try {
            $confirmed = $payments->confirm($reservation, [
                'method' => str_starts_with($sourceId, 'ype_') ? 'yape' : 'card',
                'provider' => 'culqi',
                'provider_reference' => $chargeId,
                'provider_payload' => $culqi->safePayload($charge),
            ]);
        } catch (HttpExceptionInterface $exception) {
            if (in_array($exception->getStatusCode(), [409, 422], true)) {
                $culqi->releaseCharge($reservation->fresh() ?? $reservation, $chargeId, $cents, 'solicitud_comprador');

                return response()->json(['message' => __('payments.expired_returned')], 422);
            }
            throw $exception;
        }
        $payment = Payment::query()->where('provider_reference', $chargeId)->first();
        $settled = $confirmed->status === 'confirmed'
            && $payment !== null
            && (int) $payment->reservation_id === (int) $reservation->id
            && in_array($payment->status, ['confirmed', 'succeeded'], true);
        if (! $settled) {
            $culqi->releaseCharge($reservation->fresh() ?? $reservation, $chargeId, $cents, 'duplicado');

            return response()->json(['message' => __('payments.extra_returned')], 422);
        }
        if (! $wasConfirmed) {
            $culqi->recordConfirmation($confirmed, $payment);
        }

        return response()->json(['redirect' => route('tickets.show', $confirmed->code)]);
    }

    private function payable(Reservation $reservation): bool
    {
        return $reservation->status === 'pending_payment' && ! $reservation->expires_at?->isPast();
    }

    private function authorizeReservation(Request $request, Reservation $reservation): void
    {
        if ($reservation->user_id) {
            abort_unless($request->user()?->id === $reservation->user_id, 403);
        }
    }
}
