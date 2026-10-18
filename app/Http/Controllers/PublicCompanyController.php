<?php

namespace App\Http\Controllers;

use App\Models\CompanyReview;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
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
        $completedReservations = collect();
        $hasUpcomingTrip = false;
        if (auth()->check()) {
            $completedReservations = $this->reservationsForCompany(auth()->user(), $organization->id)
                ->with(['companyReview', 'departure.transportRoute.originPort', 'departure.transportRoute.destinationPort', 'airDeparture.airRoute'])
                ->where(function (Builder $query) {
                    $query->whereHas('departure', fn (Builder $departure) => $departure->where('status', 'completed'))
                        ->orWhereHas('airDeparture', fn (Builder $departure) => $departure->where('status', 'completed'));
                })
                ->latest('id')
                ->get();
            $hasUpcomingTrip = $this->reservationsForCompany(auth()->user(), $organization->id)
                ->where(function (Builder $query) {
                    $query->whereHas('departure', fn (Builder $departure) => $departure->whereIn('status', ['scheduled', 'boarding']))
                        ->orWhereHas('airDeparture', fn (Builder $departure) => $departure->whereIn('status', ['scheduled', 'boarding']));
                })->exists();
        }

        return view('companies.show', compact('organization', 'reviews', 'average', 'completedReservations', 'hasUpcomingTrip'));
    }

    public function review(Request $request, Organization $organization): RedirectResponse
    {
        abort_unless($organization->type === 'transport_company' && $organization->status === 'active', 404);
        $data = $request->validate([
            'reservation_id' => ['required', 'integer', 'exists:reservations,id'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['required', 'string', 'min:10', 'max:1000'],
        ]);
        $reservation = $this->reservationsForCompany($request->user(), $organization->id)
            ->whereKey($data['reservation_id'])
            ->where(function (Builder $query) {
                $query->whereHas('departure', fn (Builder $departure) => $departure->where('status', 'completed'))
                    ->orWhereHas('airDeparture', fn (Builder $departure) => $departure->where('status', 'completed'));
            })->firstOrFail();

        if (CompanyReview::where('reservation_id', $reservation->id)->exists()) {
            return back()->with('success', 'Este viaje ya tiene una opinión registrada.');
        }

        CompanyReview::create($data + [
            'organization_id' => $organization->id,
            'user_id' => $request->user()->id,
            'status' => 'pending',
        ]);

        return redirect()->to(route('companies.show', $organization).'#opiniones')
            ->with('success', 'Tu opinión fue enviada y está pendiente de revisión.');
    }

    private function reservationsForCompany(User $user, int $organizationId): Builder
    {
        return Reservation::query()
            ->where(function (Builder $query) use ($user) {
                $query->where('user_id', $user->id)->orWhere('contact_email', $user->email);
            })
            ->where('status', 'confirmed')
            ->where(function ($query) use ($organizationId) {
                $query->whereHas('departure.transportRoute', fn (Builder $route) => $route->where('organization_id', $organizationId))
                    ->orWhereHas('airDeparture.airRoute', fn (Builder $route) => $route->where('organization_id', $organizationId));
            });
    }
}
