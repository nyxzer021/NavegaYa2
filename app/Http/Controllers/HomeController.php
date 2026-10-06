<?php

namespace App\Http\Controllers;

use App\Models\Advertisement;
use App\Models\AirDeparture;
use App\Models\AirRoute;
use App\Models\DestinationCity;
use App\Models\DestinationListing;
use App\Models\HomeHeroSlide;
use App\Models\Port;
use App\Models\RouteDeparture;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController
{
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

        $departures = RouteDeparture::with(['transportRoute.originPort', 'transportRoute.destinationPort', 'vessel.organization', 'reservations.seats'])
            ->where('status', 'scheduled')->where('is_published', true)
            ->whereHas('transportRoute.organization', fn ($organization) => $organization->where('is_marketplace_paused', false))
            ->where('departure_at', '>=', now())
            ->when($origin, fn ($q) => $q->whereHas('transportRoute.originPort', fn ($p) => $p->where('city', $origin)))
            ->when($destination, fn ($q) => $q->whereHas('transportRoute.destinationPort', fn ($p) => $p->where('city', $destination)))
            ->when($date, fn ($q) => $q->whereDate('departure_at', $date))
            ->orderBy('departure_at')->limit(12)->get();
        $airDepartures = AirDeparture::with(['airRoute', 'aircraft.organization', 'reservations.seats'])
            ->where('status', 'scheduled')->where('is_published', true)
            ->whereHas('airRoute.organization', fn ($organization) => $organization->where('is_marketplace_paused', false))
            ->where('departure_at', '>=', now())
            ->when($origin, fn ($q) => $q->whereHas('airRoute', fn ($r) => $r->where('origin_city', $origin)))
            ->when($destination, fn ($q) => $q->whereHas('airRoute', fn ($r) => $r->where('destination_city', $destination)))
            ->when($date, fn ($q) => $q->whereDate('departure_at', $date))
            ->orderBy('departure_at')->limit(12)->get();
        $cities = DestinationCity::where('is_active', true)->orderBy('name')->take(6)->get();
        $ads = Advertisement::with('city')->active()->forPlacement('home_hero')->inRandomOrder()->limit(2)->get();
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
        $featuredListings = DestinationListing::with(['city', 'images'])->where('is_active', true)->where('is_featured', true)->orderBy('sort_order')->limit(6)->get();
        $todayDepartures = $departures->filter(fn ($departure) => $departure->departure_at->isToday());
        $upcomingDepartures = $departures->filter(fn ($departure) => $departure->departure_at->isAfter(today()));
        $riverNotice = SystemSetting::value('river_notice', 'Temporada de navegación estable. Confirma el estado del río y la salida con el operador antes de dirigirte al terminal.');
        $activePorts = Port::where('is_active', true)->count();
        $festivals = collect([
            ['date' => '24 JUN', 'name' => 'Fiesta de San Juan', 'place' => 'Amazonía peruana', 'description' => 'Gastronomía, música y tradición en las principales ciudades amazónicas.'],
            ['date' => '05 ENE', 'name' => 'Aniversario de Iquitos', 'place' => 'Loreto', 'description' => 'Actividades culturales y celebraciones junto al río Amazonas.'],
            ['date' => 'FEB', 'name' => 'Carnaval Amazónico', 'place' => 'Amazonía peruana', 'description' => 'Comparsas, danzas y expresiones culturales de la selva.'],
        ]);

        return view('home', compact('departures', 'airDepartures', 'cities', 'ads', 'citiesForSearch', 'slides', 'corridors', 'featuredListings', 'todayDepartures', 'upcomingDepartures', 'riverNotice', 'activePorts', 'festivals', 'hasSearch', 'transport') + ['heroDuration' => (int) SystemSetting::value('home_hero_duration', '4'), 'whatsappNumber' => SystemSetting::value('whatsapp_number'), 'whatsappMessage' => SystemSetting::value('whatsapp_message', 'Hola, necesito ayuda con mi viaje en NavegaYA.')]);
    }
}
