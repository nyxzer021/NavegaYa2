<?php

namespace App\Http\Controllers;

use App\Models\Advertisement;
use App\Models\DestinationCity;
use App\Models\DestinationListing;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GuideController extends Controller
{
    public function index(Request $request): View
    {
        $cityId = $request->integer('ciudad') ?: null;
        $type = in_array($request->string('tipo')->toString(), ['lodging', 'gastronomy', 'attraction'], true)
            ? $request->string('tipo')->toString() : null;

        $listings = DestinationListing::with(['city', 'images'])
            ->where('is_active', true)
            ->when($cityId, fn ($query) => $query->where('destination_city_id', $cityId))
            ->when($type, fn ($query) => $query->where('type', $type))
            // Five directory entries + the permanent advertising card + up to three
            // sponsored cards keep the three-column grid at a maximum of three rows.
            ->orderByDesc('is_featured')->orderBy('sort_order')->orderBy('name')->paginate(5)->withQueryString();

        $cities = DestinationCity::where('is_active', true)->orderBy('name')->get();
        $ads = Advertisement::with('city')->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('starts_on')->orWhereDate('starts_on', '<=', today()))
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', today()))
            ->when($cityId, fn ($query) => $query->where(fn ($q) => $q->whereNull('destination_city_id')->orWhere('destination_city_id', $cityId)))
            ->latest()->take(3)->get();
        $ads->each(fn ($ad) => $ad->increment('views'));

        return view('guide.index', compact('listings', 'cities', 'ads', 'cityId', 'type'));
    }
}
