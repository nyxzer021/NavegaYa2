<?php

namespace App\Http\Controllers;

use App\Models\AirRoute;
use App\Models\Discount;
use App\Models\DiscountRoute;
use App\Models\Organization;
use App\Models\RouteFare;
use App\Models\TransportRoute;
use App\Support\AdminScope;
use App\Support\BaggageAllowance;
use App\Support\FareTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CompanyFareController extends Controller
{
    public function index(): View
    {
        $organization = $this->organization();

        return view('company.fares.index', [
            'organization' => $organization,
            'baggage' => BaggageAllowance::for($organization),
            'riverRoutes' => $organization->transportRoutes()->with(['originPort', 'destinationPort'])->orderBy('code')->get(),
            'airRoutes' => $organization->airRoutes()->orderBy('code')->get(),
            'fares' => RouteFare::query()
                ->where('organization_id', $organization->id)
                ->with(['transportRoute.originPort', 'transportRoute.destinationPort', 'airRoute'])
                ->orderBy('space_type')
                ->orderBy('passenger_type')
                ->get(),
            'discounts' => Discount::query()
                ->where('organization_id', $organization->id)
                ->with('assignments.transportRoute.originPort', 'assignments.transportRoute.destinationPort', 'assignments.airRoute')
                ->latest()
                ->get(),
        ]);
    }

    public function storeFare(Request $request): RedirectResponse
    {
        $organization = $this->organization();
        $data = $request->validate([
            'route' => ['required', 'string'],
            'space_type' => ['required', 'in:seat,hammock,cabin_bed'],
            'passenger_type' => ['required', 'in:adult,child,student,senior,infant'],
            'amount' => ['required', 'numeric', 'min:0', 'max:999999'],
            'includes_meal' => ['nullable', 'boolean'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ]);
        [$riverId, $airId] = $this->routePair($organization, $data['route']);
        if ($airId !== null && $data['space_type'] !== 'seat') {
            return back()->withInput()->withErrors(['space_type' => 'En una ruta aérea solo se tarifa el asiento.']);
        }
        $attributes = [
            'organization_id' => $organization->id,
            'transport_route_id' => $riverId,
            'air_route_id' => $airId,
            'space_type' => $data['space_type'],
            'passenger_type' => $data['passenger_type'],
            'amount' => round((float) $data['amount'], 2),
            'includes_meal' => $request->boolean('includes_meal'),
            'starts_on' => $data['starts_on'] ?? null,
            'ends_on' => $data['ends_on'] ?? null,
        ];
        FareTable::guardOverlap($attributes);
        RouteFare::create($attributes);

        return redirect()->route('company.fares.index')->with('success', 'Tarifa guardada.');
    }

    public function destroyFare(RouteFare $routeFare): RedirectResponse
    {
        $organization = $this->organization();
        abort_unless((int) $routeFare->organization_id === (int) $organization->id, 404);
        $routeFare->delete();

        return redirect()->route('company.fares.index')->with('success', 'Tarifa eliminada.');
    }

    public function storeDiscount(Request $request): RedirectResponse
    {
        $organization = $this->organization();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['required', 'in:percent,amount'],
            'value' => ['required', 'numeric', 'min:0', 'max:999999'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['in:web,counter,agency'],
            'requires_document' => ['nullable', 'boolean'],
            'usage_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'routes' => ['nullable', 'array'],
            'routes.*' => ['string'],
        ]);
        if ($data['kind'] === 'percent' && (float) $data['value'] > 100) {
            return back()->withInput()->withErrors(['value' => 'El porcentaje no puede pasar de 100.']);
        }
        DB::transaction(function () use ($organization, $data, $request): void {
            $discount = Discount::create([
                'organization_id' => $organization->id,
                'name' => $data['name'],
                'kind' => $data['kind'],
                'value' => round((float) $data['value'], 2),
                'channels' => array_values(array_unique($data['channels'])),
                'requires_document' => $request->boolean('requires_document'),
                'usage_limit' => $data['usage_limit'] ?? null,
                'starts_on' => $data['starts_on'] ?? null,
                'ends_on' => $data['ends_on'] ?? null,
                'status' => 'active',
            ]);
            foreach ($data['routes'] ?? [] as $key) {
                if ($key === '') {
                    continue;
                }
                [$riverId, $airId] = $this->routePair($organization, $key);
                DiscountRoute::create([
                    'discount_id' => $discount->id,
                    'transport_route_id' => $riverId,
                    'air_route_id' => $airId,
                ]);
            }
        });

        return redirect()->route('company.fares.index')->with('success', 'Descuento guardado.');
    }

    public function destroyDiscount(Discount $discount): RedirectResponse
    {
        $organization = $this->organization();
        abort_unless((int) $discount->organization_id === (int) $organization->id, 404);
        $discount->delete();

        return redirect()->route('company.fares.index')->with('success', 'Descuento eliminado.');
    }

    private function organization(): Organization
    {
        $id = AdminScope::organizationId(auth()->user());
        abort_unless($id, 403);

        return Organization::query()->whereKey($id)->firstOrFail();
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private function routePair(Organization $organization, string $key): array
    {
        if (str_starts_with($key, 'river-')) {
            $route = TransportRoute::query()->where('organization_id', $organization->id)->find(substr($key, 6));
            abort_unless($route, 404);

            return [$route->id, null];
        }
        if (str_starts_with($key, 'air-')) {
            $route = AirRoute::query()->where('organization_id', $organization->id)->find(substr($key, 4));
            abort_unless($route, 404);

            return [null, $route->id];
        }
        abort(404);
    }
}
