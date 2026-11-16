<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\AgencyInvitation;
use App\Models\AgencyLot;
use App\Models\Organization;
use App\Models\OrganizationSite;
use App\Models\RouteDeparture;
use App\Models\User;
use App\Notifications\AgencySellerInvitation;
use App\Services\AgencyLotDesk;
use App\Services\AgencySale;
use App\Services\CounterSale;
use App\Support\AdminScope;
use App\Support\VesselLayout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CompanyAgencyController extends Controller
{
    public function index(Request $request, AgencySale $sales, CounterSale $counter): View
    {
        $organization = $this->organization($request);
        $agencies = Agency::query()
            ->where('organization_id', $organization->id)
            ->with(['lots.departure.transportRoute.originPort', 'lots.departure.transportRoute.destinationPort', 'lots.departure.vessel', 'lots.holds.seat', 'sellers', 'suspendedBy.roles'])
            ->orderBy('name')
            ->get();
        $tallies = [];
        foreach ($agencies as $agency) {
            foreach ($agency->lots as $lot) {
                $tallies[$lot->id] = $sales->tally($lot);
            }
        }
        $sites = OrganizationSite::query()->where('organization_id', $organization->id)->where('is_active', true)->orderBy('name')->get();
        $sellers = User::query()
            ->whereNull('agency_id')
            ->whereHas('roles', fn ($query) => $query->where('code', 'company_counter')->where('role_user.organization_id', $organization->id))
            ->orderBy('name')
            ->get();
        $departures = $this->departures($organization->id);
        $picked = $departures->firstWhere('id', $request->integer('salida'));
        $decks = $picked ? $this->decks($picked) : [];
        $hammocks = $picked
            ? $picked->vessel->seats->where('space_type', 'hammock')->where('is_available', true)->count()
            : 0;

        return view('company.agencies.index', [
            'organization' => $organization,
            'agencies' => $agencies,
            'tallies' => $tallies,
            'sites' => $sites,
            'sellers' => $sellers,
            'departures' => $departures,
            'picked' => $picked,
            'decks' => $decks,
            'hammocks' => $hammocks,
            'busyIds' => $picked ? $counter->busySeatIdsFor($picked->id) : [],
            'focus' => $request->integer('agencia'),
            'activeAgencies' => $agencies->where('status', 'active')->count(),
            'pendingLots' => $agencies->flatMap->lots->where('payment_status', 'pending')->count(),
            'freeSeats' => (int) collect($tallies)->sum('free'),
        ]);
    }

    public function lots(Request $request, AgencySale $sales): View
    {
        $agency = $this->sellerAgency($request);
        $organization = $agency->organization;
        $lots = $agency->lots()->with(['departure.transportRoute.originPort', 'departure.transportRoute.destinationPort', 'departure.vessel', 'holds.seat'])->latest()->get();
        $tallies = [];
        foreach ($lots as $lot) {
            $tallies[$lot->id] = $sales->tally($lot);
        }

        return view('company.agencies.lots', compact('organization', 'agency', 'lots', 'tallies'));
    }

    public function store(Request $request): RedirectResponse
    {
        $organization = $this->organization($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'ruc' => ['required', 'digits:11'],
            'contact_name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:30'],
            'max_surcharge_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
        Agency::create([
            'organization_id' => $organization->id,
            'name' => $data['name'],
            'ruc' => $data['ruc'],
            'contact_name' => $data['contact_name'],
            'phone' => $data['phone'],
            'status' => 'active',
            'max_surcharge_percent' => $data['max_surcharge_percent'] ?? 0,
        ]);

        return back()->with('success', __('agencies.created'));
    }

    public function update(Request $request, Agency $agency): RedirectResponse
    {
        $this->owned($request, $agency);
        $data = $request->validate([
            'status' => ['required', 'in:active,suspended'],
            'max_surcharge_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'suspension_reason' => ['required_if:status,suspended', 'nullable', 'string', 'min:3', 'max:255'],
        ]);
        if ($agency->isSuspendedByPlatform()) {
            if ($data['status'] === 'active') {
                throw ValidationException::withMessages(['status' => __('agencies.platform_locked')]);
            }
            $agency->update(['max_surcharge_percent' => $data['max_surcharge_percent']]);

            return back()->with('success', __('agencies.updated'));
        }
        $agency->update([
            'status' => $data['status'],
            'max_surcharge_percent' => $data['max_surcharge_percent'],
            'suspension_reason' => $data['status'] === 'suspended' ? $data['suspension_reason'] : null,
            'suspended_at' => $data['status'] === 'suspended' ? now() : null,
            'suspended_by_user_id' => $data['status'] === 'suspended' ? $request->user()->id : null,
        ]);

        return back()->with('success', __('agencies.updated'));
    }

    public function invite(Request $request, Agency $agency): RedirectResponse
    {
        $this->owned($request, $agency);
        $data = $request->validate(['email' => ['required', 'email', 'max:150']]);
        $invitation = AgencyInvitation::create([
            'agency_id' => $agency->id,
            'email' => $data['email'],
            'token' => Str::random(64),
            'expires_at' => now()->addDays(3),
        ]);
        $url = route('agency.invitation', $invitation->token);
        Notification::route('mail', $invitation->email)->notify(new AgencySellerInvitation($url, $agency->name));

        return back()->with('success', __('agencies.invited', ['email' => $invitation->email]))->with('invite_url', $url);
    }

    public function storeLot(Request $request, Agency $agency, AgencyLotDesk $desk): RedirectResponse
    {
        $organization = $this->owned($request, $agency);
        $data = $request->validate([
            'departure_id' => ['required', 'integer'],
            'seats' => ['nullable', 'array'],
            'seats.*' => ['integer'],
            'hammocks' => ['nullable', 'integer', 'min:0', 'max:200'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'payment_status' => ['required', 'in:pending,paid'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $departure = RouteDeparture::with('transportRoute')->find($data['departure_id']);
        if ($departure === null || (int) $departure->transportRoute->organization_id !== (int) $organization->id) {
            abort($departure === null ? 422 : 403);
        }

        try {
            $desk->assign(
                $agency,
                $departure,
                array_map('intval', $data['seats'] ?? []),
                (int) ($data['hammocks'] ?? 0),
                number_format((float) $data['unit_price'], 2, '.', ''),
                $data['payment_status'],
                $data['note'] ?? null,
            );
        } catch (ValidationException $exception) {
            return back()->withInput()->withErrors($exception->errors());
        }

        return back()->with('success', __('agencies.lot_created'));
    }

    public function pay(Request $request, AgencyLot $lot): RedirectResponse
    {
        $this->owned($request, $lot->agency);
        $data = $request->validate(['payment_status' => ['required', 'in:pending,paid']]);
        $lot->update(['payment_status' => $data['payment_status']]);

        return back()->with('success', __('agencies.payment_saved'));
    }

    public function release(Request $request, AgencyLot $lot, AgencyLotDesk $desk): RedirectResponse
    {
        $this->owned($request, $lot->agency);
        $data = $request->validate([
            'seats' => ['required', 'array', 'min:1'],
            'seats.*' => ['integer'],
            'note' => ['required', 'string', 'min:3', 'max:255'],
        ]);
        try {
            $desk->release($request->user(), $lot, array_map('intval', $data['seats']), $data['note']);
        } catch (ValidationException $exception) {
            return back()->withInput()->withErrors($exception->errors());
        }

        return back()->with('success', __('agencies.released_done'));
    }

    public function assignSeller(Request $request, OrganizationSite $site): RedirectResponse
    {
        $organization = $this->organization($request);
        abort_unless((int) $site->organization_id === (int) $organization->id, 403);
        $data = $request->validate(['user_id' => ['required', 'integer']]);
        $seller = User::query()->whereKey($data['user_id'])->whereNull('agency_id')->first();
        $belongs = $seller?->roles()->where('code', 'company_counter')->where('role_user.organization_id', $organization->id)->exists();
        abort_unless($belongs, 403);
        $seller->forceFill(['organization_site_id' => $site->id])->save();

        return back()->with('success', __('agencies.seller_assigned'));
    }

    private function organization(Request $request): Organization
    {
        abort_unless($request->user()?->isCompanyAdmin(), 403);
        $organizationId = AdminScope::organizationId($request->user());
        abort_unless($organizationId, 403);

        return Organization::query()->where('status', 'active')->findOrFail($organizationId);
    }

    private function owned(Request $request, Agency $agency): Organization
    {
        $organization = $this->organization($request);
        abort_unless((int) $agency->organization_id === (int) $organization->id, 403);

        return $organization;
    }

    private function sellerAgency(Request $request): Agency
    {
        $user = $request->user();
        $organizationId = AdminScope::organizationId($user);
        $agency = Agency::query()->whereKey($user?->agency_id)->where('organization_id', $organizationId)->first();
        abort_unless($agency, 403);

        return $agency;
    }

    /**
     * @return Collection<int, RouteDeparture>
     */
    private function departures(int $organizationId)
    {
        return RouteDeparture::query()
            ->whereHas('transportRoute', fn ($query) => $query->where('organization_id', $organizationId))
            ->where('departure_at', '>', now())
            ->where('is_published', true)
            ->whereIn('status', CounterSale::OPEN_STATUSES)
            ->with(['transportRoute.originPort', 'transportRoute.destinationPort', 'vessel.seats'])
            ->orderBy('departure_at')
            ->limit(40)
            ->get();
    }

    /**
     * @return list<array{deck: string, plan: array<string, mixed>}>
     */
    private function decks(RouteDeparture $departure): array
    {
        $decks = [];
        $seats = $departure->vessel->seats->where('space_type', 'seat')->sortBy(['deck', 'row_position', 'column_position']);
        foreach ($seats->groupBy('deck') as $deck => $deckSeats) {
            $decks[] = [
                'deck' => (string) $deck,
                'plan' => VesselLayout::grid($departure->vessel, (string) $deck, $deckSeats),
            ];
        }

        return $decks;
    }
}
