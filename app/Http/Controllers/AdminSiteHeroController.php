<?php

namespace App\Http\Controllers;

use App\Models\SiteHero;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AdminSiteHeroController
{
    public const PAGES = [
        'about' => 'Quiénes somos',
        'routes' => 'Rutas fluviales',
        'air' => 'Rutas aéreas',
        'companies' => 'Empresas',
        'discover' => 'Descubrir la Amazonía',
        'ports' => 'Puertos y aeródromos',
        'guide' => 'Guía Loreto',
        'check_ticket' => 'Consultar pasaje',
        'affiliate' => 'Afiliar empresa',
    ];

    public function index(): View
    {
        abort_unless(request()->user()?->roles()->where('code', 'super_admin')->exists(), 403);

        return view('admin.site-heroes.index', [
            'pages' => self::PAGES,
            'heroes' => SiteHero::query()->get()->keyBy('key'),
        ]);
    }

    public function update(Request $request, string $key): RedirectResponse
    {
        abort_unless($request->user()?->roles()->where('code', 'super_admin')->exists(), 403);
        abort_unless(array_key_exists($key, self::PAGES), 404);

        $data = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=1200,min_height=500'],
        ]);

        $hero = SiteHero::query()->firstOrNew(['key' => $key]);
        if ($hero->exists && $hero->image_path) {
            Storage::disk('public')->delete($hero->image_path);
        }
        $hero->image_path = $data['image']->store('site-heroes', 'public');
        $hero->save();

        return back()->with('success', 'Portada de '.$this->label($key).' actualizada.');
    }

    private function label(string $key): string
    {
        return self::PAGES[$key] ?? $key;
    }
}
