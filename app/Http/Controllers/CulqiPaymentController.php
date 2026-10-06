<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Reservation;
use App\Services\BookingPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CulqiPaymentController extends Controller
{
    public function order(Request $request, string $code): JsonResponse
    {
        abort_unless(config('services.culqi.enabled') && config('services.culqi.secret_key'), 503);
        $reservation = Reservation::where('code', $code)->firstOrFail();
        $this->authorizeReservation($request, $reservation);
        abort_unless($reservation->status === 'pending_payment' && ! $reservation->expires_at?->isPast(), 422);
        $names = preg_split('/\s+/', trim($reservation->contact_name), 2);
        $response = Http::withToken(config('services.culqi.secret_key'))->acceptJson()->asJson()->timeout(20)
            ->post(rtrim(config('services.culqi.api_url'), '/').'/orders', [
                'amount' => (int) round((float) $reservation->total_amount * 100),
                'currency_code' => 'PEN',
                'description' => "Pasajes NavegaYA {$reservation->code}",
                'order_number' => $reservation->code,
                'client_details' => [
                    'first_name' => $names[0] ?? $reservation->contact_name,
                    'last_name' => $names[1] ?? '-',
                    'email' => $reservation->contact_email,
                    'phone_number' => preg_replace('/\D+/', '', $reservation->contact_phone),
                ],
                'expiration_date' => $reservation->expires_at->timestamp,
                'confirm' => true,
                'metadata' => ['reservation_code' => $reservation->code],
            ]);
        if ($response->failed()) {
            return response()->json(['message' => $response->json('user_message') ?? $response->json('merchant_message') ?? 'No se pudo generar el código PagoEfectivo.'], 422);
        }
        $order = $response->json();
        abort_unless(($order['object'] ?? null) === 'order' && filled($order['id'] ?? null), 422);
        Payment::updateOrCreate(
            ['provider_reference' => $order['id']],
            [
                'reservation_id' => $reservation->id, 'method' => 'pagoefectivo', 'provider' => 'culqi',
                'amount' => $reservation->total_amount, 'currency_code' => 'PEN', 'status' => 'pending',
                'provider_payload' => $order,
            ],
        );

        return response()->json([
            'order_id' => $order['id'],
            'payment_code' => $order['payment_code'] ?? null,
            'expires_at' => $reservation->expires_at->toIso8601String(),
        ]);
    }

    public function charge(Request $request, string $code, BookingPaymentService $payments): JsonResponse
    {
        abort_unless(config('services.culqi.enabled') && config('services.culqi.secret_key'), 503);
        $data = $request->validate(['source_id' => ['required', 'string', 'max:120']]);
        $reservation = Reservation::with('seats')->where('code', $code)->firstOrFail();
        $this->authorizeReservation($request, $reservation);
        abort_unless($reservation->status === 'pending_payment' && ! $reservation->expires_at?->isPast(), 422);

        $names = preg_split('/\s+/', trim($reservation->contact_name), 2);
        $response = Http::withToken(config('services.culqi.secret_key'))
            ->acceptJson()->asJson()->timeout(20)
            ->post(rtrim(config('services.culqi.api_url'), '/').'/charges', [
                'amount' => (int) round((float) $reservation->total_amount * 100),
                'currency_code' => 'PEN',
                'email' => $reservation->contact_email,
                'source_id' => $data['source_id'],
                'capture' => true,
                'description' => "Pasajes NavegaYA {$reservation->code}",
                'metadata' => ['reservation_code' => $reservation->code],
                'antifraud_details' => [
                    'country_code' => 'PE',
                    'first_name' => $names[0] ?? $reservation->contact_name,
                    'last_name' => $names[1] ?? '-',
                    'email' => $reservation->contact_email,
                    'phone_number' => preg_replace('/\D+/', '', $reservation->contact_phone),
                ],
            ]);

        if ($response->failed()) {
            return response()->json(['message' => $response->json('user_message') ?? $response->json('merchant_message') ?? 'Culqi no pudo procesar el pago.'], 422);
        }
        $charge = $response->json();
        abort_unless(($charge['object'] ?? null) === 'charge' && ($charge['state'] ?? null) === 'Exitosa', 422);

        $payments->confirm($reservation, [
            'method' => str_starts_with($data['source_id'], 'ype_') ? 'yape' : 'card',
            'provider' => 'culqi',
            'provider_reference' => $charge['id'],
            'provider_payload' => $charge,
        ]);

        return response()->json(['redirect' => route('tickets.show', $reservation->code)]);
    }

    private function authorizeReservation(Request $request, Reservation $reservation): void
    {
        if ($reservation->user_id) {
            abort_unless($request->user()?->id === $reservation->user_id, 403);
        }
    }
}
