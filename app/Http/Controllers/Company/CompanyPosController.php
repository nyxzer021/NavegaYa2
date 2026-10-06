<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\ReservationSeat;
use App\Models\RouteDeparture;
use App\Services\BookingPaymentService;
use App\Support\AdminScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CompanyPosController extends Controller
{
    public function index(Request $request): View
    {
        $organizationId = AdminScope::organizationId($request->user());
        abort_unless($organizationId, 403);
        $organization = Organization::with('vessels.basePort')->findOrFail($organizationId);
        $departures = RouteDeparture::query()
            ->whereHas('transportRoute', fn ($query) => $query->where('organization_id', $organizationId))
            ->whereDate('departure_at', today())->where('status', 'scheduled')
            ->with(['transportRoute.originPort', 'transportRoute.destinationPort', 'vessel.seats'])
            ->orderBy('departure_at')->get();
        $selectedDeparture = $departures->firstWhere('id', $request->integer('departure')) ?? $departures->first();
        $occupiedSeatIds = $selectedDeparture
            ? ReservationSeat::where('route_departure_id', $selectedDeparture->id)->pluck('vessel_seat_id')->all()
            : [];

        return view('company.pos.index', compact('organization', 'departures', 'selectedDeparture', 'occupiedSeatIds'));
    }

    public function store(Request $request, BookingPaymentService $payments): RedirectResponse
    {
        $data = $request->validate([
            'departure_id' => ['required', 'integer', 'exists:route_departures,id'],
            'seat_id' => ['required', 'integer', 'exists:vessel_seats,id'],
            'document_type' => ['required', 'in:DNI,CE,Pasaporte'],
            'document_number' => ['required', 'string', 'max:40'],
            'passenger_name' => ['required', 'string', 'max:150'],
            'passenger_age' => ['required', 'integer', 'min:0', 'max:120'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:150'],
        ]);
        $organizationId = AdminScope::organizationId($request->user());
        $departure = RouteDeparture::with('transportRoute')->findOrFail($data['departure_id']);
        abort_unless((int) $departure->transportRoute->organization_id === (int) $organizationId && $departure->departure_at->isToday(), 403);

        $reservation = DB::transaction(function () use ($data, $departure) {
            $expired = Reservation::where('route_departure_id', $departure->id)->where('status', 'pending_payment')->where('expires_at', '<=', now())->pluck('id');
            ReservationSeat::whereIn('reservation_id', $expired)->delete();
            Reservation::whereIn('id', $expired)->update(['status' => 'expired']);
            $seat = $departure->vessel->seats()->whereKey($data['seat_id'])->where('is_available', true)->lockForUpdate()->firstOrFail();
            abort_if(ReservationSeat::where('route_departure_id', $departure->id)->where('vessel_seat_id', $seat->id)->exists(), 422, 'El asiento ya no está disponible.');
            $code = 'POS-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
            $reservation = Reservation::create([
                'route_departure_id' => $departure->id,
                'code' => $code,
                'contact_name' => $data['passenger_name'],
                'contact_email' => $data['email'] ?: strtolower($code).'@pos.navegaya.test',
                'contact_phone' => $data['phone'],
                'contact_document' => $data['document_number'],
                'total_amount' => $departure->fare,
                'status' => 'pending_payment',
                'sales_channel' => 'counter',
            ]);
            ReservationSeat::create([
                'reservation_id' => $reservation->id,
                'route_departure_id' => $departure->id,
                'vessel_seat_id' => $seat->id,
                'passenger_name' => $data['passenger_name'],
                'document_type' => $data['document_type'],
                'document_number' => $data['document_number'],
                'passenger_age' => $data['passenger_age'],
            ]);

            return $reservation;
        });

        $organization = Organization::findOrFail($organizationId);
        $payments->confirm($reservation, [
            'method' => 'cash_dock',
            'provider' => 'pos',
            'provider_reference' => 'CASH-'.$reservation->code,
            'provider_payload' => ['registered_by' => $request->user()->id],
        ], (float) $organization->physical_sales_commission_rate);

        return redirect()->route('tickets.show', $reservation->code)->with('success', 'Venta en muelle registrada y boleto emitido.');
    }
}
