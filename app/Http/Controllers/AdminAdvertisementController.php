<?php

namespace App\Http\Controllers;

use App\Models\Advertisement;
use App\Models\DestinationCity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminAdvertisementController
{
    public function index(): View
    {
        return view('admin.ads.index', ['ads' => Advertisement::with('city')->latest()->get(), 'cities' => DestinationCity::where('is_active', true)->orderBy('name')->get()]);
    }

    public function store(Request $r): RedirectResponse
    {
        $d = $r->validate(['destination_city_id' => ['required', 'exists:destination_cities,id'], 'business_name' => ['required', 'max:150'], 'category' => ['required', 'max:40'], 'title' => ['required', 'max:160'], 'description' => ['nullable', 'max:1000'], 'target_url' => ['nullable', 'url'], 'image_url' => ['nullable', 'url', 'max:500'], 'starts_on' => ['nullable', 'date'], 'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on']]);
        Advertisement::create($d);

        return back()->with('success', 'Anuncio enviado para aprobación.');
    }

    public function approve(Advertisement $ad): RedirectResponse
    {
        $this->onlyMainAdmin();
        $ad->update(['status' => 'active']);

        return back()->with('success', 'Anuncio aprobado y publicado.');
    }

    public function pause(Advertisement $ad): RedirectResponse
    {
        $this->onlyMainAdmin();
        $ad->update(['status' => 'paused']);

        return back()->with('success', 'Anuncio pausado.');
    }

    public function resume(Advertisement $ad): RedirectResponse
    {
        $this->onlyMainAdmin();
        $ad->update(['status' => 'active']);

        return back()->with('success', 'Anuncio reactivado.');
    }

    private function onlyMainAdmin(): void
    {
        abort_unless(auth()->user()?->roles()->where('code', 'super_admin')->exists(), 403);
    }
}
