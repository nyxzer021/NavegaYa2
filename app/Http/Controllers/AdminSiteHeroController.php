<?php

namespace App\Http\Controllers;

use App\Models\PublicPage;
use App\Models\SiteHero;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AdminSiteHeroController
{
    public const PAGES = [
        'home' => 'Inicio', 'about' => 'Quiénes somos', 'routes' => 'Rutas',
        'companies' => 'Empresas', 'discover' => 'Descubrir', 'guide' => 'Guía Loreto',
        'ports' => 'Puertos', 'advertise' => 'Anúnciate', 'check_ticket' => 'Mis viajes',
        'affiliate' => 'Afiliar empresa',
    ];

    public function index(): View
    {
        return view('admin.site-heroes.index', [
            'pages' => self::PAGES,
            'heroes' => SiteHero::whereNotNull('page_key')->get()->keyBy('page_key'),
            'customPages' => PublicPage::with('hero')->orderBy('title')->get(),
        ]);
    }

    public function update(Request $request, string $key): RedirectResponse
    {
        abort_unless(array_key_exists($key, self::PAGES), 404);
        $this->save($request, SiteHero::firstOrNew(['page_key' => $key]));

        return back()->with('success', 'Portada actualizada.');
    }

    public function updatePage(Request $request, PublicPage $page): RedirectResponse
    {
        $this->save($request, SiteHero::firstOrNew(['public_page_id' => $page->id]));

        return back()->with('success', 'Portada de la página actualizada.');
    }

    public function reset(string $key): RedirectResponse
    {
        abort_unless(array_key_exists($key, self::PAGES), 404);
        SiteHero::where('page_key', $key)->delete();

        return back()->with('success', 'Se restauró la portada predeterminada.');
    }

    public function resetPage(PublicPage $page): RedirectResponse
    {
        $page->hero()?->delete();

        return back()->with('success', 'Se restauró la portada predeterminada.');
    }

    private function save(Request $request, SiteHero $hero): void
    {
        $data = $request->validate([
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'focal_point' => ['required', 'in:'.implode(',', SiteHero::FOCAL_POINTS)],
            'alt_text' => ['nullable', 'string', 'max:180'],
        ]);
        if ($request->hasFile('image')) {
            if ($hero->image_path && str_starts_with($hero->image_path, '/storage/')) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $hero->image_path));
            }
            $data['image_path'] = Storage::url($request->file('image')->store('site-heroes', 'public'));
        }
        unset($data['image']);
        $hero->fill($data)->save();
    }
}
