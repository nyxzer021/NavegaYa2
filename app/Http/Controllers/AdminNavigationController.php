<?php

namespace App\Http\Controllers;

use App\Models\NavigationItem;
use App\Models\PublicPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminNavigationController extends Controller
{
    public function index(Request $request): View
    {
        $items = NavigationItem::orderBy('position')->orderBy('id')->get();
        $pages = PublicPage::orderBy('title')->get();
        $tab = in_array($request->string('tab')->toString(), ['overview', 'menu', 'pages'], true) ? $request->string('tab')->toString() : 'overview';

        return view('admin.navigation.index', [
            'items' => $items,
            'pages' => $pages,
            'activeTab' => $tab,
            'selectedItem' => $items->firstWhere('id', $request->integer('item')),
            'selectedPage' => $pages->firstWhere('id', $request->integer('page')),
        ]);
    }

    public function storeItem(Request $r): RedirectResponse
    {
        $d = $this->itemData($r);
        $item = NavigationItem::create($d);

        return redirect()->route('admin.navigation.index', ['tab' => 'menu', 'item' => $item])
            ->with('success', 'Pestaña publicada en el menú.');
    }

    public function updateItem(Request $r, NavigationItem $item): RedirectResponse
    {
        $item->update($this->itemData($r));

        return back()->with('success', 'Pestaña actualizada.');
    }

    public function destroyItem(NavigationItem $item): RedirectResponse
    {
        $item->delete();

        return back()->with('success', 'Pestaña eliminada.');
    }

    public function storePage(Request $r): RedirectResponse
    {
        $d = $this->pageData($r);
        $d['slug'] = $d['slug'] ?: Str::slug($d['title']);
        $page = PublicPage::create($d);

        return redirect()->route('admin.navigation.index', ['tab' => 'pages', 'page' => $page])
            ->with('success', 'Página creada; ya puedes asignarla a una pestaña.');
    }

    public function updatePage(Request $r, PublicPage $page): RedirectResponse
    {
        $d = $this->pageData($r, $page);
        $d['slug'] = $d['slug'] ?: Str::slug($d['title']);
        $page->update($d);

        return back()->with('success', 'Página actualizada.');
    }

    public function destroyPage(PublicPage $page): RedirectResponse
    {
        NavigationItem::where('target_type', 'page')->where('target', $page->slug)->delete();
        $page->delete();

        return back()->with('success', 'Página eliminada.');
    }

    private function itemData(Request $r): array
    {
        $data = $r->validate([
            'label' => ['required', 'string', 'max:60'],
            'label_en' => ['nullable', 'string', 'max:60'],
            'target_type' => ['required', Rule::in(['route', 'page', 'url'])],
            'target' => ['required', 'string', 'max:500'],
            'position' => ['required', 'integer', 'min:0', 'max:999'],
            'is_active' => ['nullable', 'boolean'],
            'opens_new_tab' => ['nullable', 'boolean'],
        ]);

        $targetExists = match ($data['target_type']) {
            'route' => \Route::has($data['target']),
            'page' => PublicPage::where('slug', $data['target'])
                ->when($r->boolean('is_active'), fn ($query) => $query->where('is_published', true))
                ->exists(),
            'url' => filter_var($data['target'], FILTER_VALIDATE_URL) !== false,
        };

        if (! $targetExists) {
            throw ValidationException::withMessages([
                'target' => $data['target_type'] === 'page'
                    ? 'Selecciona una página personalizada publicada.'
                    : 'El destino seleccionado no es válido.',
            ]);
        }

        return $data + ['is_active' => $r->boolean('is_active'), 'opens_new_tab' => $r->boolean('opens_new_tab')];
    }

    private function pageData(Request $r, ?PublicPage $page = null): array
    {
        return $r->validate(['title' => ['required', 'string', 'max:150'], 'slug' => ['nullable', 'string', 'max:160', Rule::unique('public_pages', 'slug')->ignore($page?->id)], 'summary' => ['nullable', 'string', 'max:500'], 'content' => ['nullable', 'string'], 'is_published' => ['nullable', 'boolean']]) + ['is_published' => $r->boolean('is_published')];
    }
}
