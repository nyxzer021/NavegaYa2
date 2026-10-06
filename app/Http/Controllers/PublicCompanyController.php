<?php

namespace App\Http\Controllers;

use App\Models\CompanyReview;
use App\Models\Organization;
use App\Models\Reservation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicCompanyController
{
    public function index(): View
    {
        $companies = Organization::withCount(['vessels', 'transportRoutes', 'aircraft', 'airRoutes'])
            ->withAvg(['reviews as average_rating' => fn ($query) => $query->where('status', 'published')], 'rating')
            ->where('type', 'transport_company')->where('status', 'active')->orderBy('commercial_name')->get();

        return view('companies.index', compact('companies'));
    }

    public function show(Organization $organization): View
    {
        abort_unless($organization->type === 'transport_company' && $organization->status === 'active', 404);
        $organization->load([
            'vessels.basePort',
            'vessels.departures',
            'transportRoutes.originPort',
            'transportRoutes.destinationPort',
            'transportRoutes.departures.vessel.organization',
            'transportRoutes.departures.reservations.seats',
            'aircraft',
            'airRoutes.departures.aircraft.organization',
            'airRoutes.departures.reservations.seats',
        ]);
        $reviews = CompanyReview::with('user')->where('organization_id', $organization->id)->where('status', 'published')->latest()->get();
        $average = round((float) $reviews->avg('rating'), 1);
        $canReview = auth()->check() && $this->isEligible(auth()->user()->email, $organization->id);

        return view('companies.show', compact('organization', 'reviews', 'average', 'canReview'));
    }

    public function review(Request $request, Organization $organization): RedirectResponse
    {
        abort_unless($organization->type === 'transport_company' && $organization->status === 'active', 404);
        abort_unless($this->isEligible($request->user()->email, $organization->id), 403);
        $data = $request->validate(['rating' => ['required', 'integer', 'between:1,5'], 'comment' => ['required', 'string', 'min:10', 'max:1000']]);
        CompanyReview::updateOrCreate(['organization_id' => $organization->id, 'user_id' => $request->user()->id], $data + ['status' => 'pending']);

        return back()->with('success', 'Tu valoración fue enviada para revisión.');
    }

    private function isEligible(string $email, int $organizationId): bool
    {
        return Reservation::where('contact_email', $email)->where('status', 'confirmed')
            ->where(function ($query) use ($organizationId) {
                $query->whereHas('departure', fn ($departure) => $departure->where('departure_at', '<', now())->whereHas('transportRoute', fn ($route) => $route->where('organization_id', $organizationId)))
                    ->orWhereHas('airDeparture', fn ($departure) => $departure->where('departure_at', '<', now())->whereHas('airRoute', fn ($route) => $route->where('organization_id', $organizationId)));
            })
            ->exists();
    }
}
