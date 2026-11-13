<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\ReservationSeat;
use App\Models\RouteDeparture;
use App\Services\BookingPaymentService;
use App\Services\HammockSale;
use App\Support\AdminScope;
use App\Support\FareTable;
use DomainException;
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
        $organization = Organization::with('vessels.basePort')->where('status', 'active')->findOrFail($organizationId);
        $expired = Reservation::where('status', 'pending_payment')->where('expires_at', '<=', now())->pluck('id');
        Reservation::expirePending($expired);
        $departures = RouteDeparture::query()
            ->whereHas('transportRoute', fn ($query) => $query->where('organization_id', $organizationId))
            ->whereDate('departure_at', today())->whereIn('status', ['scheduled', 'delayed', 'rescheduled'])->where('is_published', true)
            ->with(['transportRoute.originPort', 'transportRoute.destinationPort', 'vessel.seats'])
            ->orderBy('departure_at')->get();
        $selectedDeparture = $departures->firstWhere('id', $request->integer('departure')) ?? $departures->first();
        $occupiedSeatIds = $selectedDeparture
            ? ReservationSeat::where('route_departure_id', $selectedDeparture->id)->pluck('vessel_seat_id')->all()
            : [];
        $upcoming = RouteDeparture::query()
            ->whereHas('transportRoute', fn ($query) => $query->where('organization_id', $organizationId))
            ->where('departure_at', '>', now()->endOfDay())
            ->where('is_published', true)
            ->whereIn('status', ['scheduled', 'delayed', 'rescheduled'])
            ->with(['transportRoute.originPort', 'transportRoute.destinationPort', 'vessel'])
            ->orderBy('departure_at')->limit(3)->get();

        return view('company.pos.index', compact('organization', 'departures', 'selectedDeparture', 'occupiedSeatIds', 'upcoming'));
    }

    public function store(Request $request, BookingPaymentService $payments): RedirectResponse
    {
        $data = $request->validate([
            'departure_id' => ['required', 'integer', 'exists:route_departures,id'],
            'place' => ['nullable', 'in:hammock'],
            'seat_id' => ['required_unless:place,hammock', 'nullable', 'integer', 'exists:vessel_seats,id'],
            'document_type' => ['required', 'in:DNI,CE,Pasaporte'],
            'document_number' => ['required', 'string', 'max:40'],
            'passenger_name' => ['required', 'string', 'max:150'],
            'passenger_age' => ['required', 'integer', 'min:0', 'max:120'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:150'],
        ]);
        $organizationId = AdminScope::organizationId($request->user());
        abort_unless(Organization::query()->whereKey($organizationId)->where('status', 'active')->exists(), 403);
        $departure = RouteDeparture::with('transportRoute')->findOrFail($data['departure_id']);
        abort_unless((int) $departure->transportRoute->organization_id === (int) $organizationId && $departure->departure_at->isToday(), 403);
        abort_unless($departure->is_published && in_array($departure->status, ['scheduled', 'delayed', 'rescheduled'], true), 422, 'La salida no está habilitada para ventas.');

        try {
            $reservation = DB::transaction(function () use ($data, $departure, $request) {
                if ($request->input('place') === 'hammock') {
                    return app(HammockSale::class)->reserve($departure, [
                        'name' => $data['passenger_name'],
                        'email' => ($data['email'] ?? null) ?: 'venta-'.strtolower($data['document_number']).'@pos.navegaya.test',
                        'phone' => $data['phone'],
                        'document' => $data['document_number'],
                        'document_type' => $data['document_type'],
                        'age' => (int) $data['passenger_age'],
                        'channel' => 'counter',
                        'expires_at' => null,
                    ]);
                }

                $expired = Reservation::where('route_departure_id', $departure->id)->where('status', 'pending_payment')->where('expires_at', '<=', now())->pluck('id');
                Reservation::expirePending($expired);
                $seat = $departure->vessel->seats()->whereKey($data['seat_id'])->where('is_available', true)->lockForUpdate()->firstOrFail();
                abort_if(ReservationSeat::where('route_departure_id', $departure->id)->where('vessel_seat_id', $seat->id)->exists(), 422, 'El asiento ya no está disponible.');
                $space = $seat->space_type ?: 'seat';
                $departure->loadMissing('transportRoute');
                $amount = FareTable::price($departure->transportRoute, $space, 'adult', $departure->departure_at, $space === 'seat' ? (float) $departure->fare : null);
                if ($amount === null) {
                    throw new DomainException('Esa clase no está disponible en esta ruta.');
                }
                FareTable::assertVests($departure, FareTable::occupants($seat));
                $code = 'POS-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
                $reservation = Reservation::create([
                    'route_departure_id' => $departure->id,
                    'code' => $code,
                    'contact_name' => $data['passenger_name'],
                    'contact_email' => ($data['email'] ?? null) ?: strtolower($code).'@pos.navegaya.test',
                    'contact_phone' => $data['phone'],
                    'contact_document' => $data['document_number'],
                    'base_amount' => $amount,
                    'discount_amount' => 0,
                    'total_amount' => $amount,
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
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['place' => $exception->getMessage()]);
        }

        $payments->confirm($reservation, [
            'method' => 'cash_dock',
            'provider' => 'pos',
            'provider_reference' => 'CASH-'.$reservation->code,
            'provider_payload' => ['registered_by' => $request->user()->id],
        ], 0.0);

        return redirect()->route('tickets.show', $reservation->code)->with('success', 'Venta en muelle registrada y boleto emitido.');
    }
}
