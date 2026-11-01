<?php

namespace App\Http\Controllers;

use App\Models\CheckoutOrder;
use App\Models\Reservation;
use App\Models\SystemSetting;
use App\Services\BookingPaymentService;
use App\Support\BoardingQr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CartController extends Controller
{
    public function index(Request $request): View
    {
        $reservations = $this->reservations($request);
        $subtotal = (float) $reservations->sum('total_amount');
        $percent = (float) SystemSetting::value('platform_fee_percent', '10');
        $fee = round($subtotal * $percent / 100, 2);

        return view('cart.index', compact('reservations', 'subtotal', 'percent', 'fee') + ['total' => $subtotal + $fee]);
    }

    public function remove(Request $request, Reservation $reservation): RedirectResponse
    {
        $cartReservationIds = collect($request->session()->get('cart_reservation_ids', []))->map(fn ($id) => (int) $id);
        abort_unless($cartReservationIds->contains($reservation->id), 404);
        $ids = $cartReservationIds->reject(fn ($id) => $id === $reservation->id)->values()->all();
        $request->session()->put('cart_reservation_ids', $ids);
        if ($reservation->status === 'pending_payment' && ! $reservation->checkout_order_id) {
            $reservation->seats()->delete();
            $reservation->update(['status' => 'cancelled']);
        }

        return back()->with('success', 'Pasaje retirado del carrito.');
    }

    public function checkout(Request $request): RedirectResponse
    {
        $reservations = $this->reservations($request);
        abort_if($reservations->isEmpty(), 422, 'El carrito está vacío.');
        $subtotal = (float) $reservations->sum('total_amount');
        $percent = (float) SystemSetting::value('platform_fee_percent', '10');
        $fee = round($subtotal * $percent / 100, 2);
        $first = $reservations->first();
        $order = DB::transaction(function () use ($reservations, $subtotal, $percent, $fee, $first) {
            $order = CheckoutOrder::create(['user_id' => auth()->id(), 'code' => 'ORD-'.now()->format('ymd').'-'.Str::upper(Str::random(7)), 'contact_name' => $first->contact_name, 'contact_email' => $first->contact_email, 'contact_phone' => $first->contact_phone, 'subtotal_amount' => $subtotal, 'platform_fee_amount' => $fee, 'total_amount' => $subtotal + $fee, 'platform_fee_percent' => $percent, 'expires_at' => now()->addMinutes(15)]);
            Reservation::whereIn('id', $reservations->pluck('id'))->update(['checkout_order_id' => $order->id]);

            return $order;
        });
        $request->session()->put('active_checkout_order', $order->id);

        return redirect()->route('cart.payment', $order->code);
    }

    public function payment(Request $request, string $code): View
    {
        $order = $this->order($request, $code);

        return view('cart.payment', ['order' => $order, 'sandboxEnabled' => config('payments.sandbox_enabled'), 'culqiEnabled' => config('services.culqi.enabled')]);
    }

    public function sandboxConfirm(Request $request, string $code, BookingPaymentService $payments): RedirectResponse
    {
        abort_unless(config('payments.sandbox_enabled'), 404);
        $data = $request->validate(['method' => ['required', 'in:yape,plin,card']]);
        $order = $this->order($request, $code);
        if ($order->status === 'confirmed') {
            return redirect()->route('cart.tickets', $order->code);
        }
        abort_unless($order->status === 'pending_payment', 422);
        $order->load('reservations');
        foreach ($order->reservations as $reservation) {
            if ($reservation->status === 'confirmed') {
                continue;
            }
            $payments->confirm($reservation, [
                'method' => $data['method'],
                'provider' => 'sandbox',
                'provider_reference' => 'DEMO-'.$reservation->code,
                'provider_payload' => ['mode' => 'sandbox'],
            ]);
        }
        $order->refresh()->load('reservations');
        $order->update([
            'subtotal_amount' => $order->reservations->sum(fn (Reservation $reservation): float => (float) ($reservation->base_amount ?? $reservation->total_amount)),
            'platform_fee_amount' => $order->reservations->sum(fn (Reservation $reservation): float => (float) $reservation->service_fee_amount),
            'total_amount' => $order->reservations->sum(fn (Reservation $reservation): float => (float) $reservation->total_amount),
            'status' => 'confirmed',
            'expires_at' => null,
        ]);
        $request->session()->forget('cart_reservation_ids');

        return redirect()->route('cart.tickets', $order->code);
    }

    public function pdf(Request $request, string $code)
    {
        $order = $this->order($request, $code);
        abort_unless($order->status === 'confirmed', 404);
        $order->load(['reservations.departure.transportRoute.originPort', 'reservations.departure.transportRoute.destinationPort', 'reservations.departure.vessel.organization', 'reservations.airDeparture.airRoute', 'reservations.airDeparture.aircraft.organization', 'reservations.seats.seat', 'reservations.seats.aircraftSeat', 'reservations.seats.ticket']);
        $qrDataUris = [];
        foreach ($order->reservations as $reservation) {
            foreach ($reservation->seats as $seat) {
                $qrDataUris[$seat->ticket->id] = BoardingQr::dataUri($seat->ticket);
            }
        }

        return app('dompdf.wrapper')->loadView('cart.pdf', compact('order', 'qrDataUris'))->setPaper('a4')->download('boletos-'.$order->code.'.pdf');
    }

    public function tickets(Request $request, string $code): View
    {
        $order = $this->order($request, $code);
        abort_unless($order->status === 'confirmed', 404);
        $order->load(['reservations.departure.transportRoute.originPort', 'reservations.departure.transportRoute.destinationPort', 'reservations.departure.vessel', 'reservations.airDeparture.airRoute', 'reservations.airDeparture.aircraft', 'reservations.seats.seat', 'reservations.seats.aircraftSeat', 'reservations.seats.ticket']);

        return view('cart.tickets', compact('order'));
    }

    private function reservations(Request $request)
    {
        $ids = $request->session()->get('cart_reservation_ids', []);

        return Reservation::with(['departure.transportRoute.originPort', 'departure.transportRoute.destinationPort', 'departure.vessel', 'airDeparture.airRoute', 'airDeparture.aircraft', 'seats.seat', 'seats.aircraftSeat'])->whereIn('id', $ids)->where('status', 'pending_payment')->whereNull('checkout_order_id')->where('expires_at', '>', now())->get();
    }

    private function order(Request $request, string $code): CheckoutOrder
    {
        $order = CheckoutOrder::with('reservations.seats')->where('code', $code)->firstOrFail();
        if ($order->user_id) {
            abort_unless(auth()->id() === $order->user_id, 403);
        } else {
            abort_unless((int) $request->session()->get('active_checkout_order') === $order->id, 403);
        }

        return $order;
    }
}
