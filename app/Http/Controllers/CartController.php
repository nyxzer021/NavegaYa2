<?php

namespace App\Http\Controllers;

use App\Mail\TicketConfirmedMail;
use App\Models\CheckoutOrder;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\SystemSetting;
use App\Models\Ticket;
use App\Support\BoardingQr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
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
        $ids = collect($request->session()->get('cart_reservation_ids', []))->reject(fn ($id) => (int) $id === $reservation->id)->values()->all();
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

    public function sandboxConfirm(Request $request, string $code): RedirectResponse
    {
        abort_unless(config('payments.sandbox_enabled'), 404);
        $data = $request->validate(['method' => ['required', 'in:yape,plin,card']]);
        $order = $this->order($request, $code);
        if ($order->status === 'confirmed') {
            return redirect()->route('cart.tickets', $order->code);
        }abort_unless($order->status === 'pending_payment', 422);
        DB::transaction(function () use ($order, $data) {
            $order->refresh()->load('reservations.seats.ticket');
            Payment::create(['reservation_id' => $order->reservations->first()->id, 'checkout_order_id' => $order->id, 'method' => $data['method'], 'provider' => 'sandbox', 'provider_reference' => 'DEMO-'.Str::upper(Str::random(12)), 'amount' => $order->total_amount, 'status' => 'confirmed', 'paid_at' => now(), 'provider_payload' => ['mode' => 'sandbox', 'platform_fee' => $order->platform_fee_amount]]);
            foreach ($order->reservations as $reservation) {
                $reservation->update(['status' => 'confirmed', 'expires_at' => null]);
                foreach ($reservation->seats as $seat) {
                    if (! $seat->ticket) {
                        Ticket::create(['reservation_seat_id' => $seat->id, 'code' => 'BOL-'.now()->format('ymd').'-'.Str::upper(Str::random(6)), 'boarding_token' => Str::upper(Str::random(32)), 'status' => 'confirmed', 'issued_at' => now()]);
                    }
                }
            }$order->update(['status' => 'confirmed', 'expires_at' => null]);
        });
        $order->refresh()->load(['reservations.seats.seat', 'reservations.seats.aircraftSeat', 'reservations.seats.ticket']);
        foreach ($order->reservations as $reservation) {
            try {
                Mail::to($reservation->contact_email)->send(new TicketConfirmedMail($reservation));
            } catch (\Throwable $exception) {
                logger()->warning('No se pudo enviar el correo del boleto.', ['reservation' => $reservation->code, 'error' => $exception->getMessage()]);
            }
        }$request->session()->forget('cart_reservation_ids');

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
            abort_unless(auth()->id() === $order->user_id,403);
        } else {
            abort_unless((int) $request->session()->get('active_checkout_order') === $order->id,403);
        }

return $order;
    }
}
