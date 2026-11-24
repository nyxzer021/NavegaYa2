<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Services\MercadoPagoCheckout;
use App\Services\MercadoPagoException;
use App\Services\MercadoPagoGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MercadoPagoPaymentController extends Controller
{
    public function start(Request $request, string $code, MercadoPagoGateway $mercadoPago): RedirectResponse
    {
        abort_unless($mercadoPago->ready(), 503, __('payments.mp_off'));
        $reservation = Reservation::query()->where('code', $code)->firstOrFail();
        $this->authorizeReservation($request, $reservation);
        if (! $this->payable($reservation)) {
            return back()->with('payment_error', __('payments.not_payable'));
        }
        try {
            $url = $mercadoPago->createCheckoutUrl($reservation);
        } catch (MercadoPagoException $exception) {
            return back()->with('payment_error', $exception->getMessage());
        }

        return redirect()->away($url);
    }

    public function returned(Request $request, string $code, MercadoPagoGateway $mercadoPago, MercadoPagoCheckout $checkout): RedirectResponse
    {
        $reservation = Reservation::query()->where('code', $code)->firstOrFail();
        $this->authorizeReservation($request, $reservation);
        if (! $mercadoPago->ready()) {
            return redirect()->route('payments.show', $reservation->code)->with('payment_error', __('payments.mp_off'));
        }
        if ($reservation->status === 'confirmed') {
            return redirect()->route('tickets.show', $reservation->code);
        }
        $paymentId = (string) $request->query('payment_id', '');
        if (preg_match('/^\d{1,20}$/', $paymentId) !== 1) {
            return redirect()->route('payments.show', $reservation->code)->with('payment_error', __('payments.mp_pending'));
        }
        try {
            $result = $checkout->settle($paymentId, null, $reservation);
        } catch (MercadoPagoException $exception) {
            return redirect()->route('payments.show', $reservation->code)->with('payment_error', $exception->getMessage());
        }
        if ($result === 'confirmed' || ($result === 'duplicate' && $reservation->fresh()->status === 'confirmed')) {
            return redirect()->route('tickets.show', $reservation->code);
        }
        $message = $result === 'returned' ? __('payments.expired_returned') : __('payments.mp_pending');

        return redirect()->route('payments.show', $reservation->code)->with('payment_error', $message);
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
