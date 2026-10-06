<?php

namespace App\Http\Controllers;

use App\Models\NavigationItem;
use App\Models\PublicPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminNavigationController extends Controller
{
    public function index(): View
    {
        return view('admin.navigation.index', ['items' => NavigationItem::orderBy('position')->get(), 'pages' => PublicPage::orderBy('title')->get()]);
    }

    public function storeItem(Request $r): RedirectResponse
    {
        $d = $this->itemData($r);
        NavigationItem::create($d);

        return back()->with('success', 'Pestaña agregada al menú.');
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
        PublicPage::create($d);

        return back()->with('success', 'Página creada; ya puedes asignarla a una pestaña.');
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
        return $r->validate(['label' => ['required', 'string', 'max:60'], 'label_en' => ['nullable', 'string', 'max:60'], 'target_type' => ['required', Rule::in(['route', 'page', 'url'])], 'target' => ['required', 'string', 'max:500'], 'position' => ['required', 'integer', 'min:0', 'max:999'], 'is_active' => ['nullable', 'boolean'], 'opens_new_tab' => ['nullable', 'boolean']]) + ['is_active' => $r->boolean('is_active'), 'opens_new_tab' => $r->boolean('opens_new_tab')];
    }

    private function pageData(Request $r, ?PublicPage $page = null): array
    {
        return $r->validate(['title' => ['required', 'string', 'max:150'], 'slug' => ['nullable', 'string', 'max:160', Rule::unique('public_pages', 'slug')->ignore($page?->id)], 'summary' => ['nullable', 'string', 'max:500'], 'content' => ['nullable', 'string'], 'is_published' => ['nullable', 'boolean']]) + ['is_published' => $r->boolean('is_published')];
    }
}
