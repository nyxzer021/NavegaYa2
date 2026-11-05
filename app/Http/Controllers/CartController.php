<?php

namespace App\Http\Controllers;

use App\Models\CheckoutOrder;
use App\Models\Reservation;
use App\Services\BookingPaymentService;
use App\Services\MarketplaceSales;
use App\Support\BoardingQr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(private readonly MarketplaceSales $sales) {}

    public function index(Request $request): View
    {
        $reservations = $this->reservations($request);
        $priced = $this->priced($reservations);

        return view('cart.index', [
            'reservations' => $reservations,
            'subtotal' => $priced['subtotal'],
            'percent' => $priced['percent'],
            'singleRate' => $priced['single_rate'],
            'fee' => $priced['fee'],
            'total' => $priced['total'],
        ]);
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
        $priced = $this->priced($reservations);
        $subtotal = $priced['subtotal'];
        $percent = $priced['percent'];
        $fee = $priced['fee'];
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
        $order->load(['reservations.departure.transportRoute.originPort', 'reservations.departure.transportRoute.destinationPort', 'reservations.departure.transportRoute.organization', 'reservations.departure.vessel', 'reservations.airDeparture.airRoute.organization', 'reservations.airDeparture.aircraft', 'reservations.seats.seat', 'reservations.seats.aircraftSeat', 'reservations.seats.ticket']);

        return view('cart.tickets', compact('order'));
    }

    private function reservations(Request $request)
    {
        $ids = $request->session()->get('cart_reservation_ids', []);

        return Reservation::with(['departure.transportRoute.originPort', 'departure.transportRoute.destinationPort', 'departure.transportRoute.organization', 'departure.vessel', 'airDeparture.airRoute.organization', 'airDeparture.aircraft', 'seats.seat', 'seats.aircraftSeat'])->whereIn('id', $ids)->where('status', 'pending_payment')->whereNull('checkout_order_id')->where('expires_at', '>', now())->get();
    }

    /**
     * @param  Collection<int, Reservation>  $reservations
     * @return array{subtotal: float, fee: float, percent: float, single_rate: bool, total: float}
     */
    private function priced(Collection $reservations): array
    {
        $subtotal = 0.0;
        $fee = 0.0;
        $percents = [];
        foreach ($reservations as $reservation) {
            $quote = $this->sales->quote($reservation);
            $subtotal += $quote['base_amount'] - $quote['discount_amount'];
            $fee += $quote['service_fee_amount'];
            $percents[] = number_format($quote['service_fee_percent'], 2, '.', '');
        }
        $subtotal = round($subtotal, 2);
        $fee = round($fee, 2);
        $unique = collect($percents)->unique()->values();
        $percent = $unique->count() === 1
            ? (float) $unique->first()
            : ($subtotal > 0 ? round($fee / $subtotal * 100, 2) : 0.0);

        return [
            'subtotal' => $subtotal,
            'fee' => $fee,
            'percent' => $percent,
            'single_rate' => $unique->count() <= 1,
            'total' => round($subtotal + $fee, 2),
        ];
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
