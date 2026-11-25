<?php

namespace App\Http\Controllers;

use App\Models\ConsumerClaim;
use App\Models\Organization;
use App\Models\Reservation;
use App\Services\ConsumerClaims;
use App\Support\DepartureSchedule;
use App\Support\LimaBusinessCalendar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ConsumerClaimController extends Controller
{
    public function __construct(private readonly ConsumerClaims $claims) {}

    public function create(Request $request): View
    {
        $selected = $request->integer('empresa') ?: null;
        $code = trim((string) $request->query('reserva', ''));
        $organization = $selected
            ? Organization::query()->where('type', 'transport_company')->find($selected)
            : null;
        $reservation = $this->reservationFor($organization, $code);
        if ($organization && $code !== '' && $reservation === null) {
            $code = '';
        }
        $companies = Organization::query()
            ->where('type', 'transport_company')
            ->orderBy('legal_name')
            ->get(['id', 'legal_name', 'ruc']);

        return view('claims.create', [
            'companies' => $companies,
            'organization' => $organization,
            'reservationCode' => $reservation?->code ?? ($organization ? $code : ''),
            'amount' => $reservation ? number_format((float) $reservation->total_amount, 2, '.', '') : '',
            'description' => $reservation ? $this->description($reservation) : '',
            'filedOn' => now()->timezone(DepartureSchedule::ZONE)->format('d/m/Y'),
            'days' => LimaBusinessCalendar::RESPONSE_BUSINESS_DAYS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'in:platform,company'],
            'organization_id' => ['nullable', 'required_if:provider,company', 'integer', 'exists:organizations,id'],
            'reservation_code' => ['nullable', 'string', 'max:40'],
            'consumer_name' => ['required', 'string', 'max:160'],
            'document_type' => ['required', 'in:dni,ce,passport'],
            'document_number' => ['required', 'string', 'max:20'],
            'address' => ['required', 'string', 'max:200'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:160'],
            'is_minor' => ['nullable', 'boolean'],
            'guardian_name' => ['nullable', 'required_if:is_minor,1', 'string', 'max:160'],
            'guardian_document' => ['nullable', 'required_if:is_minor,1', 'string', 'max:20'],
            'guardian_relationship' => ['nullable', 'required_if:is_minor,1', Rule::in(['padre', 'madre', 'apoderado'])],
            'good_kind' => ['required', 'in:product,service'],
            'amount' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'good_description' => ['required', 'string', 'min:5', 'max:500'],
            'claim_type' => ['required', 'in:reclamo,queja'],
            'detail' => ['required', 'string', 'min:10', 'max:2000'],
            'request_text' => ['required', 'string', 'min:5', 'max:1000'],
            'email_accepted' => ['accepted'],
        ]);

        $organization = null;
        if ($data['provider'] === 'company') {
            $organization = Organization::query()->where('type', 'transport_company')->find($data['organization_id']);
            if ($organization === null) {
                return back()->withInput()->withErrors(['organization_id' => __('claims.company_required')]);
            }
        }
        $code = trim((string) ($data['reservation_code'] ?? ''));
        $reservation = $code === '' ? null : Reservation::query()->where('code', $code)->first();
        if ($data['provider'] === 'company' && $code !== '' && ($reservation === null || $this->claims->organizationId($reservation) !== $organization->id)) {
            return back()->withInput()->withErrors(['reservation_code' => __('claims.reservation_mismatch')]);
        }

        $claim = $this->claims->file([
            ...$data,
            'is_minor' => $request->boolean('is_minor'),
            'organization_id' => $organization?->id,
            'reservation_id' => $reservation?->id,
            'reservation_code' => $code === '' ? null : $code,
            'guardian_name' => $request->boolean('is_minor') ? $data['guardian_name'] : null,
            'guardian_document' => $request->boolean('is_minor') ? $data['guardian_document'] : null,
            'guardian_relationship' => $request->boolean('is_minor') ? $data['guardian_relationship'] : null,
        ]);

        return redirect()->route('claims.show', $claim->public_token);
    }

    public function show(string $token): View
    {
        $claim = $this->find($token);

        return view('claims.show', $this->claims->sheet($claim));
    }

    public function pdf(string $token): Response
    {
        $claim = $this->find($token);

        $binary = app('dompdf.wrapper')
            ->loadView('claims.pdf', $this->claims->sheet($claim))
            ->setPaper('a4')
            ->output(['compress' => 0]);

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="libro-'.$claim->code.'.pdf"',
        ]);
    }

    private function find(string $token): ConsumerClaim
    {
        return ConsumerClaim::query()->where('public_token', $token)->firstOrFail();
    }

    private function reservationFor(?Organization $organization, string $code): ?Reservation
    {
        if ($organization === null || $code === '') {
            return null;
        }
        $reservation = Reservation::query()->where('code', $code)->first();
        if ($reservation === null || $this->claims->organizationId($reservation) !== $organization->id) {
            return null;
        }

        return $reservation;
    }

    private function description(Reservation $reservation): string
    {
        $reservation->loadMissing([
            'departure.transportRoute.originPort',
            'departure.transportRoute.destinationPort',
            'airDeparture.airRoute',
        ]);
        if ($reservation->isAir()) {
            $route = $reservation->airDeparture?->airRoute;

            return trim(__('claims.trip_air', [
                'origin' => $route?->origin_city ?? '',
                'destination' => $route?->destination_city ?? '',
            ]));
        }
        $route = $reservation->departure?->transportRoute;

        return trim(__('claims.trip_river', [
            'origin' => $route?->originPort?->city ?? '',
            'destination' => $route?->destinationPort?->city ?? '',
        ]));
    }
}
