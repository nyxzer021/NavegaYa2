<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\CargoShipment;
use App\Models\Organization;
use App\Models\OrganizationSite;
use App\Models\RouteDeparture;
use App\Services\CargoDesk;
use App\Support\AdminScope;
use App\Support\CargoGuideQr;
use App\Support\DepartureSchedule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CompanyCargoController extends Controller
{
    public function index(Request $request, CargoDesk $desk): View
    {
        $organization = $this->organization($request);
        $onlyLoose = $request->boolean('reasignar');
        $shipments = CargoShipment::query()
            ->where('organization_id', $organization->id)
            ->when($onlyLoose, fn ($query) => $query->where('needs_reassignment', true))
            ->with(['departure.transportRoute.originPort', 'departure.transportRoute.destinationPort', 'departure.vessel', 'events.user'])
            ->orderByDesc('needs_reassignment')
            ->latest('id')
            ->limit(40)
            ->get();
        [$start, $end] = DepartureSchedule::dayBounds();
        $departures = RouteDeparture::query()
            ->with(['transportRoute.originPort', 'transportRoute.destinationPort', 'vessel'])
            ->whereHas('transportRoute', fn ($query) => $query->where('organization_id', $organization->id))
            ->whereNotIn('status', ['cancelled', 'completed'])
            ->where('departure_at', '>=', now()->subHours(6))
            ->orderBy('departure_at')
            ->limit(40)
            ->get();
        $capacities = [];
        foreach ($departures as $departure) {
            $capacities[$departure->id] = $desk->capacity($departure);
        }
        foreach ($shipments as $shipment) {
            if ($shipment->route_departure_id && ! isset($capacities[$shipment->route_departure_id]) && $shipment->departure) {
                $capacities[$shipment->route_departure_id] = $desk->capacity($shipment->departure);
            }
        }
        $presentation = is_array($organization->public_presentation) ? $organization->public_presentation : [];
        $cargo = is_array($presentation['cargo'] ?? null) ? $presentation['cargo'] : [];
        $rates = array_filter([
            'pequeno' => $cargo['pequeno'] ?? null,
            'mediano' => $cargo['mediano'] ?? null,
            'grande' => $cargo['grande'] ?? null,
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        return view('company.cargo.index', [
            'organization' => $organization,
            'shipments' => $shipments,
            'departures' => $departures,
            'capacities' => $capacities,
            'rates' => $rates,
            'portNote' => $cargo['port_note'] ?? null,
            'sites' => $this->sites($request, $organization),
            'receivedToday' => CargoShipment::query()->where('organization_id', $organization->id)->whereBetween('received_at', [$start, $end])->count(),
            'boardedToday' => CargoShipment::query()->where('organization_id', $organization->id)->whereBetween('boarded_at', [$start, $end])->count(),
            'deliveredToday' => CargoShipment::query()->where('organization_id', $organization->id)->whereBetween('delivered_at', [$start, $end])->count(),
            'pendingPay' => CargoShipment::query()->where('organization_id', $organization->id)->where('payment_status', 'pending')->count(),
            'loose' => CargoShipment::query()->where('organization_id', $organization->id)->where('needs_reassignment', true)->count(),
            'onlyLoose' => $onlyLoose,
            'prefill' => [
                'sender_name' => $request->string('remitente')->toString(),
                'sender_document' => $request->string('documento')->toString(),
                'sender_phone' => $request->string('telefono')->toString(),
                'weight' => $request->string('peso')->toString(),
                'departure_id' => $request->string('salida')->toString(),
            ],
        ]);
    }

    public function store(Request $request, CargoDesk $desk): RedirectResponse
    {
        $organization = $this->organization($request);
        $data = $this->validated($request);
        try {
            $shipment = $desk->register($request->user(), $organization, $data);
        } catch (ValidationException $exception) {
            return back()->withInput()->withErrors($exception->errors());
        }

        return redirect()->route('company.parcels.guide', $shipment)->with('success', __('cargo.registered', ['code' => $shipment->code]));
    }

    public function assign(Request $request, CargoShipment $shipment, CargoDesk $desk): RedirectResponse
    {
        $this->owned($request, $shipment);
        $data = $request->validate(['route_departure_id' => ['required', 'integer']]);
        try {
            $desk->assign($request->user(), $shipment, $data);
        } catch (ValidationException $exception) {
            return back()->withInput()->withErrors($exception->errors());
        }

        return back()->with('success', __('cargo.assigned'));
    }

    public function board(Request $request, CargoShipment $shipment, CargoDesk $desk): RedirectResponse
    {
        $this->owned($request, $shipment);
        $desk->board($request->user(), $shipment);

        return back()->with('success', __('cargo.boarded'));
    }

    public function deliver(Request $request, CargoShipment $shipment, CargoDesk $desk): RedirectResponse
    {
        $this->owned($request, $shipment);
        $data = $request->validate([
            'collector_name' => ['nullable', 'string', 'max:150'],
            'collector_document' => ['required', 'string', 'max:40'],
            'method' => ['nullable', 'in:cash,yape,card'],
            'cash_tendered' => ['nullable', 'numeric', 'min:0'],
            'operation_number' => ['nullable', 'string', 'max:40'],
            'site_id' => ['nullable', 'integer'],
        ], [
            'collector_document.required' => 'Indica el documento de quien recoge.',
        ]);
        try {
            $desk->deliver($request->user(), $shipment, $data);
        } catch (ValidationException $exception) {
            return back()->withInput()->withErrors($exception->errors());
        }

        return back()->with('success', __('cargo.delivered'));
    }

    public function guide(Request $request, CargoShipment $shipment): View
    {
        $shipment = $this->owned($request, $shipment);
        $paper = $request->string('papel')->toString();
        if (! in_array($paper, ['80', 'a4', 'etiqueta'], true)) {
            $paper = 'a4';
        }

        return view('company.cargo.guide', [
            'shipment' => $shipment->load(['departure.transportRoute.originPort', 'departure.transportRoute.destinationPort', 'organization']),
            'paper' => $paper,
            'qr' => CargoGuideQr::dataUri(route('cargo.track', ['codigo' => $shipment->code])),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'sender_name' => ['required', 'string', 'max:150'],
            'sender_document' => ['required', 'string', 'max:40'],
            'sender_phone' => ['required', 'string', 'max:30'],
            'recipient_name' => ['required', 'string', 'max:150'],
            'recipient_document' => ['required', 'string', 'max:40'],
            'recipient_phone' => ['required', 'string', 'max:30'],
            'description' => ['required', 'string', 'max:1000'],
            'package_count' => ['required', 'integer', 'min:1', 'max:999'],
            'declared_weight_kg' => ['required', 'numeric', 'gt:0', 'max:99999.99'],
            'length_cm' => ['required', 'integer', 'min:1', 'max:999'],
            'width_cm' => ['required', 'integer', 'min:1', 'max:999'],
            'height_cm' => ['required', 'integer', 'min:1', 'max:999'],
            'fragile' => ['nullable', 'boolean'],
            'route_departure_id' => ['nullable', 'integer'],
            'amount' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'method' => ['required', 'in:cash,yape,card,recipient'],
            'cash_tendered' => ['nullable', 'numeric', 'min:0'],
            'operation_number' => ['nullable', 'string', 'max:40'],
            'site_id' => ['nullable', 'integer'],
        ], [
            'operation_number.required' => 'El número de operación es obligatorio.',
        ]);
    }

    private function organization(Request $request): Organization
    {
        abort_if($request->user()->agency_id, 403);
        $organizationId = AdminScope::organizationId($request->user());
        abort_unless($organizationId, 403);

        return Organization::query()->findOrFail($organizationId);
    }

    private function owned(Request $request, CargoShipment $shipment): CargoShipment
    {
        $organization = $this->organization($request);
        abort_unless((int) $shipment->organization_id === (int) $organization->id, 403);

        return $shipment;
    }

    /**
     * @return Collection<int, OrganizationSite>
     */
    private function sites(Request $request, Organization $organization)
    {
        $sites = OrganizationSite::query()->where('organization_id', $organization->id)->where('is_active', true)->orderBy('name')->get();
        $pinned = $request->user()->organization_site_id;
        if ($pinned && $sites->contains('id', $pinned)) {
            return $sites->where('id', $pinned)->values();
        }

        return $sites;
    }
}
