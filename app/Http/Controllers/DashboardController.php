<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View|RedirectResponse
    {
        $roles = auth()->user()->roles->pluck('code');
        if ($roles->contains('company_counter')) {
            return redirect()->route('company.counter.index');
        }
        if ($roles->contains('company_admin')) {
            return redirect()->route('company.dashboard');
        }
        if ($roles->contains('marketplace_admin')) {
            return redirect()->route('marketplace.dashboard');
        }
        if ($roles->intersect(['customer', 'traveler', 'passenger'])->isNotEmpty()) {
            return redirect()->route('travels.index');
        }

        if ($roles->contains('super_admin')) {
            return redirect()->route('admin.dashboard');
        }
        if ($roles->contains('admin')) {
            return redirect()->route('admin.dashboard');
        }

        return view('dashboard', [
            'title' => 'Mis viajes',
            'roles' => $roles,
            'travelerReservations' => Reservation::query()->where('user_id', auth()->id())
                ->with(['departure.transportRoute.originPort', 'departure.transportRoute.destinationPort', 'departure.vessel'])
                ->latest()->take(8)->get(),
        ]);
    }
}
