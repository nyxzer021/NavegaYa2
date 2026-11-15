<?php

namespace App\Http\Controllers;

use App\Models\AirDeparture;
use App\Models\AirRoute;
use App\Models\DestinationCity;
use App\Models\DestinationListing;
use App\Models\HomeHeroSlide;
use App\Models\Port;
use App\Models\RouteDeparture;
use App\Models\SystemSetting;
use App\Services\AdvertisementInventoryService;
use App\Support\DepartureSchedule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController
{
    public function __construct(private readonly AdvertisementInventoryService $advertising) {}

    public function __invoke(Request $request): View
    {
        $search = $request->validate([
            'transport' => ['nullable', 'in:river,air'],
            'origin' => ['nullable', 'string', 'max:100'],
            'destination' => ['nullable', 'string', 'max:100'],
            'date' => ['nullable', 'date'],
            'passengers' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);
        $hasSearch = $request->hasAny(['origin', 'destination', 'date', 'passengers', 'transport']);
        $transport = $search['transport'] ?? 'river';
        $origin = $search['origin'] ?? null;
        $destination = $search['destination'] ?? null;
        $date = $search['date'] ?? null;

        $departures = $this->openRiverDepartures($origin, $destination, $date)->orderBy('departure_at')->limit(12)->get();
        $airDepartures = $this->openAirDepartures($origin, $destination, $date)->orderBy('departure_at')->limit(12)->get();
        $limaToday = now()->timezone(DepartureSchedule::ZONE)->startOfDay();
        [$todayStart, $todayEnd] = DepartureSchedule::dayBounds($limaToday);
        $weekEnd = $limaToday->copy()->addDays(6)->endOfDay()->utc();
        $riverTodayCount = $hasSearch ? 0 : $this->openRiverDepartures(null, null, null)->whereBetween('departure_at', [$todayStart, $todayEnd])->count();
        $riverWeekCount = $hasSearch ? 0 : $this->openRiverDepartures(null, null, null)->whereBetween('departure_at', [$todayStart, $weekEnd])->count();
        $airTodayCount = $hasSearch ? 0 : $this->openAirDepartures(null, null, null)->whereBetween('departure_at', [$todayStart, $todayEnd])->count();
        $airWeekCount = $hasSearch ? 0 : $this->openAirDepartures(null, null, null)->whereBetween('departure_at', [$todayStart, $weekEnd])->count();
        $riverWindow = $hasSearch ? collect() : $this->openRiverDepartures(null, null, null)->whereBetween('departure_at', [$todayStart, $weekEnd])->orderBy('departure_at')->limit(24)->get();
        $airWindow = $hasSearch ? collect() : $this->openAirDepartures(null, null, null)->whereBetween('departure_at', [$todayStart, $weekEnd])->orderBy('departure_at')->limit(24)->get();
        $departurePorts = $this->openRiverDepartures(null, null, null)
            ->whereBetween('departure_at', [$todayStart, $weekEnd])
            ->get()
            ->filter(fn (RouteDeparture $departure) => $departure->transportRoute?->originPort)
            ->groupBy(fn (RouteDeparture $departure) => $departure->transportRoute->origin_port_id)
            ->map(function ($rows) {
                $port = $rows->first()->transportRoute->originPort;

                return (object) [
                    'name' => $port->name,
                    'city' => $port->city,
                    'companies' => $rows->map(fn (RouteDeparture $row) => $row->transportRoute->organization_id)->unique()->filter()->count(),
                    'departures' => $rows->count(),
                    'photo' => $this->portPhoto($port->city),
                ];
            })
            ->filter(fn (object $card) => $card->departures > 0)
            ->sortBy('city')
            ->values();
        $handKg = (int) SystemSetting::value('baggage_hand_kg', '10');
        $holdKg = (int) SystemSetting::value('baggage_hold_kg', '20');
        $baggageDetail = collect([
            $handKg > 0 ? __('public.tip_hand', ['kg' => $handKg]) : null,
            $holdKg > 0 ? __('public.tip_hold', ['kg' => $holdKg]) : null,
        ])->filter()->implode(' '.__('public.tip_and').' ');
        $embarkBaggage = $baggageDetail !== '' ? __('public.tip_baggage', ['detail' => $baggageDetail]) : null;
        $cities = DestinationCity::where('is_active', true)->orderBy('name')->take(6)->get();
        $carouselAds = $this->advertising->select('home_carousel', 6);
        if ($carouselAds->isEmpty()) {
            $carouselAds = $this->advertising->select('home_hero', 6);
        }
        $visible = $carouselAds;
        $ads = $this->advertising->select('home_recommendations', 3, $visible);
        $contextCity = $destination ?: $origin;
        $searchAd = $hasSearch ? $this->advertising->select('search_results', 1, $visible->merge($ads), $contextCity)->first() : null;
        $tripAds = $this->advertising->select('trip_complete', 3, $visible->merge($ads)->push($searchAd)->filter(), $contextCity, ['lodge_hotel', 'restaurant', 'tour_operator', 'charter_transport']);
        $popupAd = null;
        if (! $hasSearch) {
            $popupAd = $this->advertising->select('home_popup', 1, $visible->merge($ads))->first();
        }
        $this->advertising->register($carouselAds);
        $this->advertising->register($ads);
        $this->advertising->register($searchAd);
        $this->advertising->register($tripAds);
        $riverCities = Port::query()->where('is_active', true)->pluck('city');
        $airCities = AirRoute::query()->get(['origin_city', 'destination_city'])->flatMap(fn ($route) => [$route->origin_city, $route->destination_city]);
        $citiesForSearch = $riverCities->merge($airCities)->filter()->unique()->sort()->values();
        $slides = HomeHeroSlide::where('is_active', true)->orderBy('sort_order')->get();
        if ($slides->isEmpty()) {
            $slides = collect([
                ['image_path' => '/images/navegaya-hero-amazonas.png', 'title' => 'Tu viaje empieza en el río.', 'subtitle' => 'Busca rutas, compara empresas verificadas y reserva tu asiento para conocer la Amazonía a tu ritmo.', 'button_label' => 'Buscar salida', 'button_url' => '#buscar'],
                ['image_path' => '/images/navegaya-hero-rio.png', 'title' => 'La Amazonía se navega mejor.', 'subtitle' => 'Encuentra salidas verificadas y organiza tu viaje con información clara.', 'button_label' => 'Explorar rutas', 'button_url' => '/rutas-fluviales'],
                ['image_path' => '/images/navegaya-hero-atardecer.png', 'title' => 'Destinos que nacen junto al agua.', 'subtitle' => 'Viaja por rutas fluviales y descubre la riqueza de nuestra Amazonía.', 'button_label' => 'Explorar destinos', 'button_url' => '#destinos'],
            ])->map(fn ($slide) => (object) $slide);
        }
        $corridors = $departures->groupBy(fn ($departure) => $departure->transportRoute->originPort->city.' → '.$departure->transportRoute->destinationPort->city);
        $featuredListings = DestinationListing::with(['city', 'images'])->publiclyVisible()->where('is_featured', true)->orderBy('sort_order')->limit(6)->get();
        $todayDepartures = $departures->filter(fn ($departure) => $departure->departure_at->isToday());
        $upcomingDepartures = $departures->filter(fn ($departure) => $departure->departure_at->isAfter(today()));
        $riverNotice = SystemSetting::value('river_notice', 'Temporada de navegación estable. Confirma el estado del río y la salida con el operador antes de dirigirte al terminal.');
        $activePorts = Port::where('is_active', true)->count();
        $festivals = collect([
            ['date' => '24 JUN', 'name' => 'Fiesta de San Juan', 'place' => 'Amazonía peruana', 'description' => 'Gastronomía, música y tradición en las principales ciudades amazónicas.'],
            ['date' => '05 ENE', 'name' => 'Aniversario de Iquitos', 'place' => 'Loreto', 'description' => 'Actividades culturales y celebraciones junto al río Amazonas.'],
            ['date' => 'FEB', 'name' => 'Carnaval Amazónico', 'place' => 'Amazonía peruana', 'description' => 'Comparsas, danzas y expresiones culturales de la selva.'],
        ]);

        return view('home', compact('departures', 'airDepartures', 'cities', 'ads', 'searchAd', 'tripAds', 'carouselAds', 'popupAd', 'citiesForSearch', 'slides', 'corridors', 'featuredListings', 'todayDepartures', 'upcomingDepartures', 'riverNotice', 'activePorts', 'festivals', 'hasSearch', 'transport', 'riverTodayCount', 'riverWeekCount', 'airTodayCount', 'airWeekCount', 'riverWindow', 'airWindow', 'departurePorts', 'embarkBaggage') + ['heroDuration' => (int) SystemSetting::value('home_hero_duration', '4'), 'whatsappNumber' => SystemSetting::value('whatsapp_number'), 'whatsappMessage' => SystemSetting::value('whatsapp_message', 'Hola, necesito ayuda con mi viaje en NavegaYA.')]);
    }

    private function portPhoto(?string $city): ?string
    {
        if (! filled($city)) {
            return null;
        }

        $fromCity = DestinationCity::query()->where('name', $city)->value('image_url');
        if (filled($fromCity)) {
            return $fromCity;
        }

        $known = ['Nauta' => 'images/branding/banner-puertos-nauta.webp'];
        $relative = $known[$city] ?? null;

        return $relative && is_file(public_path($relative)) ? asset($relative) : null;
    }

    private function openRiverDepartures(?string $origin, ?string $destination, ?string $date): Builder
    {
        return RouteDeparture::query()
            ->with(['transportRoute.originPort', 'transportRoute.destinationPort', 'vessel.organization', 'reservations.seats'])
            ->where('status', 'scheduled')->where('is_published', true)
            ->whereHas('transportRoute.organization', fn ($organization) => $organization->where('is_marketplace_paused', false))
            ->where('departure_at', '>=', now())
            ->when($origin, fn ($query) => $query->whereHas('transportRoute.originPort', fn ($port) => $port->where('city', $origin)))
            ->when($destination, fn ($query) => $query->whereHas('transportRoute.destinationPort', fn ($port) => $port->where('city', $destination)))
            ->when($date, fn ($query) => $query->whereDate('departure_at', $date));
    }

    private function openAirDepartures(?string $origin, ?string $destination, ?string $date): Builder
    {
        return AirDeparture::query()
            ->with(['airRoute', 'aircraft.organization', 'reservations.seats'])
            ->where('status', 'scheduled')->where('is_published', true)
            ->whereHas('airRoute.organization', fn ($organization) => $organization->where('is_marketplace_paused', false))
            ->where('departure_at', '>=', now())
            ->when($origin, fn ($query) => $query->whereHas('airRoute', fn ($route) => $route->where('origin_city', $origin)))
            ->when($destination, fn ($query) => $query->whereHas('airRoute', fn ($route) => $route->where('destination_city', $destination)))
            ->when($date, fn ($query) => $query->whereDate('departure_at', $date));
    }
}
