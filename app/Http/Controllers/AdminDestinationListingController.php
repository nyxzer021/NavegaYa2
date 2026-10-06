<?php

namespace App\Http\Controllers;

use App\Models\DestinationCity;
use App\Models\DestinationListing;
use App\Models\DestinationListingImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AdminDestinationListingController
{
    private const TYPES = ['attraction' => 'Lugares turísticos', 'lodging' => 'Hoteles y hospedajes', 'gastronomy' => 'Gastronomía'];

    public function index(string $type): View
    {
        $this->type($type);

        return view('admin.destination-listings.index', ['type' => $type, 'title' => self::TYPES[$type], 'items' => DestinationListing::with(['city', 'images'])->where('type', $type)->orderBy('sort_order')->latest()->get()]);
    }

    public function create(string $type): View
    {
        $this->type($type);

        return view('admin.destination-listings.form', ['type' => $type, 'title' => self::TYPES[$type], 'item' => new DestinationListing(['is_active' => true]), 'cities' => DestinationCity::where('is_active', true)->orderBy('name')->get(), 'creating' => true]);
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        $this->type($type);
        $item = DestinationListing::create($this->data($request) + ['type' => $type]);
        $this->images($request, $item);

        return redirect()->route('admin.destination-listings.edit', [$type, $item])->with('success', 'Ficha creada.');
    }

    public function edit(string $type, DestinationListing $item): View
    {
        $this->guard($type, $item);

        return view('admin.destination-listings.form', ['type' => $type, 'title' => self::TYPES[$type], 'item' => $item->load('images'), 'cities' => DestinationCity::where('is_active', true)->orderBy('name')->get(), 'creating' => false]);
    }

    public function update(Request $request, string $type, DestinationListing $item): RedirectResponse
    {
        $this->guard($type, $item);
        $item->update($this->data($request));
        $this->images($request, $item);

        return back()->with('success', 'Ficha actualizada.');
    }

    public function destroy(string $type, DestinationListing $item): RedirectResponse
    {
        $this->guard($type, $item);
        $item->delete();

        return redirect()->route('admin.destination-listings.index', $type)->with('success', 'Ficha eliminada.');
    }

    public function cover(string $type, DestinationListing $item, DestinationListingImage $image): RedirectResponse
    {
        $this->guard($type, $item);
        abort_unless($image->destination_listing_id === $item->id, 404);
        $item->images()->update(['is_cover' => false]);
        $image->update(['is_cover' => true]);

        return back()->with('success', 'Imagen de portada actualizada.');
    }

    public function deleteImage(string $type, DestinationListing $item, DestinationListingImage $image): RedirectResponse
    {
        $this->guard($type, $item);
        abort_unless($image->destination_listing_id === $item->id, 404);
        $image->delete();

        return back()->with('success', 'Imagen eliminada.');
    }

    private function type(string $type): void
    {
        abort_unless(array_key_exists($type, self::TYPES), 404);
    }

    private function guard(string $type, DestinationListing $item): void
    {
        $this->type($type);
        abort_unless($item->type === $type, 404);
    }

    private function data(Request $r): array
    {
        $data = $r->validate(['destination_city_id' => ['required', 'exists:destination_cities,id'], 'name' => ['required', 'string', 'max:150'], 'category' => ['nullable', 'string', 'max:100'], 'description' => ['nullable', 'string', 'max:3000'], 'address' => ['nullable', 'string', 'max:250'], 'contact_phone' => ['nullable', 'string', 'max:40'], 'website_url' => ['nullable', 'url', 'max:500'], 'price_reference' => ['nullable', 'string', 'max:80'], 'opening_hours' => ['nullable', 'string', 'max:160'], 'sort_order' => ['required', 'integer', 'min:1', 'max:999'], 'is_featured' => ['nullable', 'boolean'], 'is_active' => ['nullable', 'boolean'], 'images.*' => ['nullable', 'image', 'max:5120']]);
        $data['is_featured'] = $r->boolean('is_featured');
        $data['is_active'] = $r->boolean('is_active');
        unset($data['images']);

        return $data;
    }

    private function images(Request $r, DestinationListing $item): void
    {
        foreach ($r->file('images', []) as $index => $image) {
            $item->images()->create(['path' => Storage::url($image->store('destination-listings', 'public')), 'sort_order' => $item->images()->max('sort_order') + $index + 1, 'is_cover' => $item->images()->count() === 0 && $index === 0]);
        }
    }
}
