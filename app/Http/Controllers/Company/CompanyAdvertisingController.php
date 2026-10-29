<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Advertisement;
use App\Models\Organization;
use App\Services\AdvertisementImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CompanyAdvertisingController extends Controller
{
    private const PLACEMENTS = ['home_carousel', 'home_sidebar', 'home_recommendations', 'home_popup', 'search_results', 'trip_complete', 'post_purchase', 'routes_sidebar', 'company_footer'];

    public function index(Request $request): View
    {
        $organization = $this->organization($request);
        $subscription = $this->subscription($organization);
        $benefits = $subscription?->benefits_snapshot ?: ($subscription?->plan?->benefits ?? []);

        return view('company.advertising.index', [
            'organization' => $organization,
            'subscription' => $subscription,
            'campaigns' => $organization->advertisements()->latest()->get(),
            'limit' => (int) data_get($benefits, 'ad_limit', 0),
            'allowedPlacements' => array_values(array_intersect(self::PLACEMENTS, data_get($benefits, 'allowed_placements', self::PLACEMENTS))),
            'placements' => array_intersect_key(Advertisement::PLACEMENTS, array_flip(self::PLACEMENTS)),
        ]);
    }

    public function store(Request $request, AdvertisementImageService $images): RedirectResponse
    {
        $organization = $this->organization($request);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'], 'description' => ['nullable', 'string', 'max:500'],
            'placement' => ['required', Rule::in(self::PLACEMENTS)],
            'banner_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1536', 'dimensions:min_width=800,min_height=450'],
        ]);
        $subscription = $this->subscription($organization);
        if (! $subscription) return back()->withErrors(['campaign' => 'La empresa necesita un plan publicitario activo. Solicítalo desde Mensajes comerciales.']);
        $benefits = $subscription->benefits_snapshot ?: ($subscription->plan?->benefits ?? []);
        $limit = (int) data_get($benefits, 'ad_limit', 0);
        $used = $organization->advertisements()->whereIn('status', ['lead_pending', 'active', 'paused'])->count();
        if ($limit > 0 && $used >= $limit) return back()->withErrors(['campaign' => "Tu plan permite {$limit} campaña(s) simultánea(s)."]);
        if (! in_array($data['placement'], data_get($benefits, 'allowed_placements', self::PLACEMENTS), true)) return back()->withErrors(['placement' => 'Esta ubicación no está incluida en el plan de tu empresa.']);

        $organization->advertisements()->create([
            'business_name' => $organization->commercial_name ?: $organization->legal_name, 'business_type' => 'charter_transport',
            'contact_name' => $organization->contact_name, 'phone_whatsapp' => $organization->whatsapp ?: $organization->phone,
            'email' => $organization->email, 'city_destination' => $organization->base_city, 'ruc' => $organization->ruc,
            'placements' => [$data['placement']], 'category' => 'Transporte '.($organization->modality ?: 'fluvial'),
            'title' => $data['title'], 'description' => $data['description'] ?? null,
            'target_url' => route('companies.show', $organization), 'banner_image_path' => $images->store($request->file('banner_image')),
            'monthly_fee' => 0, 'status' => 'lead_pending',
        ]);

        return back()->with('success', 'Campaña enviada a revisión. NavegaYA la publicará al aprobarla.');
    }

    public function pause(Request $request, Advertisement $advertisement): RedirectResponse
    {
        $organization = $this->organization($request);
        abort_unless((int) $advertisement->organization_id === (int) $organization->id, 404);
        $advertisement->update(['status' => 'paused']);
        return back()->with('success', 'Campaña pausada.');
    }

    private function organization(Request $request): Organization
    {
        return $request->user()->organizations()->where('organizations.type', 'transport_company')->firstOrFail();
    }

    private function subscription(Organization $organization)
    {
        return $organization->subscriptions()->with('plan')->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', today()))->latest()->first();
    }
}
