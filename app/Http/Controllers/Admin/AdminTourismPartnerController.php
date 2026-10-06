<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DestinationListing;
use Illuminate\View\View;

class AdminTourismPartnerController extends Controller
{
    public function index(): View
    {
        $partners = DestinationListing::query()
            ->with('city')
            ->whereIn('type', ['lodging', 'gastronomy', 'attraction'])
            ->orderByDesc('is_featured')
            ->orderBy('name')
            ->paginate(18);

        return view('admin.tourism-partners.index', [
            'partners' => $partners,
            'lodgingsCount' => DestinationListing::where('type', 'lodging')->where('is_active', true)->count(),
            'gastronomyCount' => DestinationListing::where('type', 'gastronomy')->where('is_active', true)->count(),
            'experiencesCount' => DestinationListing::where('type', 'attraction')->where('is_active', true)->count(),
        ]);
    }
}
