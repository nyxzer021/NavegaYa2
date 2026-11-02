<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Advertisement;
use App\Services\AdvertisementImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminAdvertisementController extends Controller
{
    public function index(Request $request): View
    {
        $ads = Advertisement::query()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('search'), fn ($query) => $query->where(fn ($nested) => $nested
                ->where('business_name', 'like', '%'.$request->string('search').'%')
                ->orWhere('contact_name', 'like', '%'.$request->string('search').'%')
                ->orWhere('phone_whatsapp', 'like', '%'.$request->string('search').'%')))
            ->latest()->paginate(15)->withQueryString();

        return view('admin.advertisements.index', [
            'ads' => $ads,
            'businessTypes' => Advertisement::BUSINESS_TYPES,
            'placements' => Advertisement::PLACEMENTS,
            'pipeline' => Advertisement::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'carouselAds' => Advertisement::query()
                ->active()
                ->where(fn ($query) => $query
                    ->whereJsonContains('placements', 'home_carousel')
                    ->orWhereJsonContains('placements', 'home_hero'))
                ->orderBy('views')
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function store(Request $request, AdvertisementImageService $images): RedirectResponse
    {
        $data = $this->validated($request);
        $data['business_type'] ??= 'lodge_hotel';
        $data['contact_name'] ??= $data['business_name'];
        $data['phone_whatsapp'] ??= 'Sin registrar';
        $data['city_destination'] ??= 'Iquitos';
        $data['placements'] ??= ['home_carousel'];
        $data['monthly_fee'] ??= 300;
        $data['status'] ??= 'lead_pending';
        $data['category'] = Advertisement::BUSINESS_TYPES[$data['business_type']];
        $data['title'] = $data['title'] ?: $data['business_name'];
        $this->ensureCarouselCapacity($data);
        if ($request->hasFile('banner_image')) {
            $data['banner_image_path'] = $images->store($request->file('banner_image'));
        }
        Advertisement::create($data);

        return back()->with('success', 'Campaña registrada.');
    }

    public function update(Request $request, Advertisement $ad, AdvertisementImageService $images): RedirectResponse
    {
        $data = $this->validated($request);
        $data['category'] = Advertisement::BUSINESS_TYPES[$data['business_type']];
        $data['title'] = $data['title'] ?: $data['business_name'];
        $this->ensureCarouselCapacity($data, $ad);
        if ($request->hasFile('banner_image')) {
            $newBannerPath = $images->store($request->file('banner_image'));
            if ($ad->banner_image_path) {
                Storage::disk('public')->delete($ad->banner_image_path);
            }
            $data['banner_image_path'] = $newBannerPath;
        }
        $ad->update($data);

        return back()->with('success', 'Campaña actualizada.');
    }

    public function approve(Advertisement $ad): RedirectResponse
    {
        $this->ensureCarouselCapacity(['status' => 'active', 'placements' => $ad->placements], $ad);
        $startsOn = $ad->starts_on ?: today();
        $subscription = $ad->organization?->subscriptions()->with('plan')->where('status', 'active')->latest()->first();
        $benefits = $subscription?->benefits_snapshot ?: ($subscription?->plan?->benefits ?? []);
        $duration = data_get($benefits, 'campaign_days');
        $endsOn = $ad->ends_on;
        if (! $endsOn && is_numeric($duration) && (int) $duration > 0) {
            $endsOn = $startsOn->copy()->addDays((int) $duration);
        }
        $ad->update(['status' => 'active', 'starts_on' => $startsOn, 'ends_on' => $endsOn]);

        return back()->with('success', 'Anuncio aprobado y publicado.');
    }

    public function pause(Advertisement $ad): RedirectResponse
    {
        $ad->update(['status' => 'paused']);

        return back()->with('success', 'Anuncio pausado.');
    }

    public function resume(Advertisement $ad): RedirectResponse
    {
        $this->ensureCarouselCapacity(['status' => 'active', 'placements' => $ad->placements], $ad);
        $ad->update(['status' => 'active']);

        return back()->with('success', 'Anuncio reactivado.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'business_name' => ['required', 'string', 'max:150'],
            'destination_city_id' => ['nullable', 'exists:destination_cities,id'],
            'business_type' => ['nullable', 'in:'.implode(',', array_keys(Advertisement::BUSINESS_TYPES))],
            'contact_name' => ['nullable', 'string', 'max:150'],
            'phone_whatsapp' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'], 'city_destination' => ['nullable', 'string', 'max:100'],
            'ruc' => ['nullable', 'digits:11'], 'placements' => ['nullable', 'array', 'min:1'],
            'placements.*' => ['in:'.implode(',', array_keys(Advertisement::PLACEMENTS))],
            'title' => ['nullable', 'string', 'max:160'], 'description' => ['nullable', 'string', 'max:1000'],
            'target_url' => ['nullable', 'url', 'max:500'], 'image_url' => ['nullable', 'url', 'max:500'],
            'banner_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=800,min_height=300'],
            'alt_text' => ['nullable', 'string', 'max:180'],
            'category' => ['nullable', 'string', 'max:40'], 'monthly_fee' => ['nullable', 'numeric', 'min:0'], 'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'status' => ['nullable', 'in:lead_pending,active,paused,expired'], 'display_order' => ['nullable', 'integer', 'min:1', 'max:999'], 'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function ensureCarouselCapacity(array $data, ?Advertisement $advertisement = null): void
    {
        $placements = $data['placements'] ?? [];
        if (($data['status'] ?? null) !== 'active' || ! array_intersect(['home_carousel', 'home_hero'], $placements)) {
            return;
        }

        $active = Advertisement::query()->active()
            ->where(fn ($query) => $query->whereJsonContains('placements', 'home_carousel')->orWhereJsonContains('placements', 'home_hero'))
            ->when($advertisement, fn ($query) => $query->whereKeyNot($advertisement->getKey()))
            ->count();

        if ($active >= 6) {
            throw ValidationException::withMessages(['status' => 'El carrusel ya tiene 6 campañas activas. Pausa una antes de activar otra.']);
        }
    }
}
