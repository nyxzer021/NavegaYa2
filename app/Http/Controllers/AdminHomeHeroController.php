<?php

namespace App\Http\Controllers;

use App\Models\HomeHeroSlide;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AdminHomeHeroController
{
    public function index(): View
    {
        return view('admin.home-hero.index', [
            'slides' => HomeHeroSlide::orderBy('sort_order')->get(),
            'duration' => (int) SystemSetting::value('home_hero_duration', '4'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, true);
        HomeHeroSlide::create($data);

        return back()->with('success', 'Imagen de portada agregada.');
    }

    public function update(Request $request, HomeHeroSlide $slide): RedirectResponse
    {
        $data = $this->validated($request, false, $slide);
        $slide->update($data);

        return back()->with('success', 'Portada actualizada.');
    }

    public function destroy(HomeHeroSlide $slide): RedirectResponse
    {
        abort_if(HomeHeroSlide::count() <= 1, 422, 'La portada debe conservar al menos una imagen.');
        $slide->delete();

        return back()->with('success', 'Imagen retirada de la portada.');
    }

    public function updateDuration(Request $request): RedirectResponse
    {
        $data = $request->validate(['duration' => ['required', 'integer', 'min:3', 'max:15']]);
        SystemSetting::updateOrCreate(['key' => 'home_hero_duration'], ['value' => $data['duration']]);

        return back()->with('success', 'Tiempo de transición actualizado.');
    }

    private function validated(Request $request, bool $creating, ?HomeHeroSlide $slide = null): array
    {
        $rules = [
            'image_path' => [$creating ? 'required_without:image' : 'nullable', 'string', 'max:2048'],
            'image' => ['nullable', 'image', 'max:5120'],
            'title' => ['required', 'string', 'max:120'],
            'subtitle' => ['nullable', 'string', 'max:280'],
            'button_label' => ['required', 'string', 'max:40'],
            'button_url' => ['required', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:1', 'max:99'],
            'is_active' => ['nullable', 'boolean'],
        ];
        $data = $request->validate($rules);
        if ($request->hasFile('image')) {
            $data['image_path'] = Storage::url($request->file('image')->store('home-hero', 'public'));
        }
        unset($data['image']);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
