<?php

namespace App\Http\Controllers;

use App\Models\DestinationCity;
use App\Models\DestinationReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AdminDestinationController
{
    public function index(): View
    {
        return view('admin.destinations.index', ['cities' => DestinationCity::orderBy('sort_order')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('admin.destinations.edit', ['city' => new DestinationCity(['is_active' => true, 'sort_order' => DestinationCity::max('sort_order') + 1]), 'creating' => true]);
    }

    public function store(Request $request): RedirectResponse
    {
        $city = DestinationCity::create($this->data($request, true));

        return redirect()->route('admin.destinations.edit', $city)->with('success', 'Destino creado. Completa o revisa su ficha pública.');
    }

    public function edit(DestinationCity $city): View
    {
        return view('admin.destinations.edit', compact('city') + ['creating' => false]);
    }

    public function update(Request $request, DestinationCity $city): RedirectResponse
    {
        $city->update($this->data($request));

        return redirect()->route('admin.destinations.index')->with('success', 'Contenido del destino actualizado.');
    }

    public function destroy(DestinationCity $city): RedirectResponse
    {
        $city->delete();

        return back()->with('success', 'Destino eliminado.');
    }

    public function reviews(): View
    {
        return view('admin.destinations.reviews', ['reviews' => DestinationReview::with(['city', 'user'])->latest()->paginate(20)]);
    }

    public function approveReview(DestinationReview $review): RedirectResponse
    {
        $review->update(['status' => 'published']);

        return back()->with('success', 'Valoración publicada.');
    }

    private function data(Request $request, bool $creating = false): array
    {
        $rules = ['name' => [$creating ? 'required' : 'sometimes', 'string', 'max:100', 'unique:destination_cities,name'.($creating ? '' : ','.$request->route('city')->id)], 'department' => ['required', 'string', 'max:100'], 'river' => ['nullable', 'string', 'max:100'], 'summary' => ['nullable', 'string', 'max:3000'], 'attractions' => ['nullable', 'string', 'max:3000'], 'lodging' => ['nullable', 'string', 'max:3000'], 'gastronomy' => ['nullable', 'string', 'max:3000'], 'image_url' => ['nullable', 'url', 'max:500'], 'image' => ['nullable', 'image', 'max:5120'], 'sort_order' => ['nullable', 'integer', 'min:1', 'max:999'], 'is_active' => ['nullable', 'boolean']];
        $data = $request->validate($rules);
        if ($request->hasFile('image')) {
            $data['image_url'] = Storage::url($request->file('image')->store('destinations', 'public'));
        }unset($data['image']);
        $data['sort_order'] = $data['sort_order'] ?? ($request->route('city')?->sort_order ?? (DestinationCity::max('sort_order') + 1));
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
