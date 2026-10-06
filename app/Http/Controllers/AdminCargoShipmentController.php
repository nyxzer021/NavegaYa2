<?php

namespace App\Http\Controllers;

use App\Models\AirDeparture;
use App\Models\CargoShipment;
use App\Models\ReservationSeat;
use App\Models\RouteDeparture;
use App\Support\AdminScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminCargoShipmentController extends Controller
{
    public function index(): View
    {
        $organizationId = AdminScope::organizationId(auth()->user());

        return view('admin.cargo.index', ['shipments' => CargoShipment::with(['departure.transportRoute.originPort', 'departure.transportRoute.destinationPort', 'airDeparture.airRoute', 'airDeparture.aircraft', 'reservationSeat.reservation'])->when($organizationId, fn ($query) => $query->where(fn ($scope) => $scope->whereHas('departure.transportRoute', fn ($route) => $route->where('organization_id', $organizationId))->orWhereHas('airDeparture.airRoute', fn ($route) => $route->where('organization_id', $organizationId))))->latest()->paginate(20)]);
    }

    public function create(): View
    {
        $organizationId = AdminScope::organizationId(auth()->user());
        $departures = RouteDeparture::with(['transportRoute.originPort', 'transportRoute.destinationPort'])
            ->where('cargo_enabled', true)->where('status', 'scheduled')
            ->when($organizationId, fn ($query) => $query->whereHas('transportRoute', fn ($route) => $route->where('organization_id', $organizationId)))
            ->orderBy('departure_at')->get();
        $airDepartures = AirDeparture::with(['airRoute', 'aircraft'])->where('cargo_enabled', true)->where('status', 'scheduled')
            ->when($organizationId, fn ($query) => $query->whereHas('airRoute', fn ($route) => $route->where('organization_id', $organizationId)))
            ->orderBy('departure_at')->get();

        return view('admin.cargo.create', compact('departures', 'airDepartures'));
    }

    public function passengerLookup(Request $request): JsonResponse
    {
        $data = $request->validate(['document' => ['required', 'string', 'max:40'], 'route_departure_id' => ['required', 'exists:route_departures,id']]);
        $organizationId = AdminScope::organizationId(auth()->user());
        $seat = ReservationSeat::with(['reservation', 'departure.transportRoute.originPort', 'departure.transportRoute.destinationPort'])
            ->where('route_departure_id', $data['route_departure_id'])->where('document_number', $data['document'])
            ->whereHas('reservation', fn ($query) => $query->where('status', 'confirmed'))
            ->when($organizationId, fn ($query) => $query->whereHas('departure.transportRoute', fn ($route) => $route->where('organization_id', $organizationId)))->first();
        if (! $seat) {
            return response()->json(['found' => false]);
        }

        return response()->json(['found' => true, 'seat_id' => $seat->id, 'passenger_name' => $seat->passenger_name, 'document' => $seat->document_number, 'phone' => $seat->reservation->contact_phone, 'reservation_code' => $seat->reservation->code, 'route' => $seat->departure->transportRoute->originPort->city.' → '.$seat->departure->transportRoute->destinationPort->city]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'transport_mode' => ['required', 'in:fluvial,aereo'], 'route_departure_id' => ['nullable', 'exists:route_departures,id'], 'air_departure_id' => ['nullable', 'exists:air_departures,id'],
            'reservation_seat_id' => ['nullable', 'exists:reservation_seats,id'], 'sender_name' => ['required', 'string', 'max:150'], 'sender_document' => ['nullable', 'string', 'max:40'], 'sender_phone' => ['required', 'string', 'max:30'],
            'recipient_name' => ['required', 'string', 'max:150'], 'recipient_document' => ['nullable', 'string', 'max:40'], 'recipient_phone' => ['nullable', 'string', 'max:30'], 'description' => ['required', 'string', 'max:1000'],
            'package_count' => ['required', 'integer', 'min:1', 'max:999'], 'declared_weight_kg' => ['required', 'numeric', 'gt:0', 'max:99999.99'],
        ]);
        if ($data['transport_mode'] === 'fluvial' && empty($data['route_departure_id'])) {
            return back()->withInput()->withErrors(['route_departure_id' => 'Selecciona una salida fluvial.']);
        }
        if ($data['transport_mode'] === 'aereo' && empty($data['air_departure_id'])) {
            return back()->withInput()->withErrors(['air_departure_id' => 'Selecciona una salida aérea.']);
        }
        if ($organizationId = AdminScope::organizationId(auth()->user())) {
            if ($data['transport_mode'] === 'fluvial') {
                $departure = RouteDeparture::with('transportRoute')->findOrFail($data['route_departure_id']);
                abort_unless($departure->transportRoute->organization_id === $organizationId, 403);
            } else {
                $departure = AirDeparture::with('airRoute')->findOrFail($data['air_departure_id']);
                abort_unless($departure->airRoute->organization_id === $organizationId, 403);
            }
        }
        if (! empty($data['reservation_seat_id'])) {
            $passenger = ReservationSeat::with('reservation')->findOrFail($data['reservation_seat_id']);
            if ($data['transport_mode'] !== 'fluvial' || (int) $passenger->route_departure_id !== (int) $data['route_departure_id']) {
                return back()->withInput()->withErrors(['reservation_seat_id' => 'El pasajero elegido debe tener un boleto confirmado para la misma salida fluvial.']);
            }
            $data['reservation_id'] = $passenger->reservation_id;
        }
        unset($data['transport_mode']);
        CargoShipment::create($data + ['code' => 'CAR-'.now()->format('ymd').'-'.Str::upper(Str::random(6)), 'received_at' => now()]);

        return redirect()->route('admin.cargo.index')->with('success', 'Carga registrada. Debe pesarse y cobrarse en terminal.');
    }

    public function verify(Request $request, CargoShipment $cargo): RedirectResponse
    {
        $data = $request->validate(['verified_weight_kg' => ['required', 'numeric', 'gt:0'], 'rate_per_kg' => ['required', 'numeric', 'min:0'], 'receipt_number' => ['required', 'string', 'max:80']]);
        $data['amount'] = $data['verified_weight_kg'] * $data['rate_per_kg'];
        $cargo->update($data + ['payment_status' => 'paid_terminal', 'status' => 'ready_to_load']);

        return back()->with('success', 'Peso, cobro y comprobante registrados.');
    }

    public function deliver(CargoShipment $cargo): RedirectResponse
    {
        $cargo->update(['status' => 'delivered', 'delivered_at' => now()]);

        return back()->with('success', 'Carga marcada como entregada.');
    }
}
