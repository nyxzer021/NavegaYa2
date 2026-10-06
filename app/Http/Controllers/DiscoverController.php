<?php

namespace App\Http\Controllers;

use App\Models\Advertisement;
use App\Models\DestinationCity;
use App\Models\DestinationReview;
use App\Models\Reservation;
use App\Models\SystemSetting;
use App\Models\TransportRoute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DiscoverController
{
    public function index(): View
    {
        $cities = DestinationCity::where('is_active', true)->orderBy('name')->get();
        $routes = TransportRoute::with(['originPort', 'destinationPort'])->where('status', 'active')->get();
        $ads = Advertisement::with('city')->where('status', 'active')->where(fn ($q) => $q->whereNull('starts_on')->orWhereDate('starts_on', '<=', today()))->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', today()))->get();
        $ads->each(fn ($ad) => $ad->increment('views'));
        $whatsappNumber = SystemSetting::value('whatsapp_number');
        $whatsappMessage = SystemSetting::value('whatsapp_message', 'Hola, necesito ayuda para planificar mi viaje con NavegaYA.');

        return view('discover.index', compact('cities', 'routes', 'ads', 'whatsappNumber', 'whatsappMessage'));
    }

    public function show(DestinationCity $city): View
    {
        abort_unless($city->is_active, 404);
        $routes = TransportRoute::with(['originPort', 'destinationPort'])->where('status', 'active')->get()->filter(fn ($route) => $route->originPort->city === $city->name || $route->destinationPort->city === $city->name);
        $reviews = DestinationReview::with('user')->where('destination_city_id', $city->id)->where('status', 'published')->latest()->get();
        $average = round((float) $reviews->avg('rating'), 1);
        $canReview = false;
        if (auth()->check()) {
            $canReview = Reservation::where('contact_email', auth()->user()->email)->where('status', 'confirmed')->whereHas('departure', fn ($departure) => $departure->where('departure_at', '<', now())->whereHas('transportRoute', fn ($route) => $route->whereHas('originPort', fn ($port) => $port->where('city', $city->name))->orWhereHas('destinationPort', fn ($port) => $port->where('city', $city->name))))->exists();
        }

return view('discover.show', compact('city', 'routes', 'reviews', 'average', 'canReview'));
    }

    public function review(Request $request, DestinationCity $city): RedirectResponse
    {
        $data = $request->validate(['rating' => ['required', 'integer', 'between:1,5'], 'comment' => ['required', 'string', 'min:10', 'max:1000']]);
        $eligible = Reservation::where('contact_email', $request->user()->email)->where('status', 'confirmed')->whereHas('departure', fn ($departure) => $departure->where('departure_at', '<', now())->whereHas('transportRoute', fn ($route) => $route->whereHas('originPort', fn ($port) => $port->where('city', $city->name))->orWhereHas('destinationPort', fn ($port) => $port->where('city', $city->name))))->exists();
        abort_unless($eligible, 403);
        DestinationReview::updateOrCreate(['destination_city_id' => $city->id, 'user_id' => $request->user()->id], $data + ['status' => 'pending']);

        return back()->with('success', 'Tu valoración fue enviada para revisión.');
    }

    public function advertisement(Advertisement $ad)
    {
        abort_unless($ad->status === 'active' && $ad->target_url,404);
        $ad->increment('clicks');

        return redirect()->away($ad->target_url);
    }
}
