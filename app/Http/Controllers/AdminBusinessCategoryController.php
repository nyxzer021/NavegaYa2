<?php

namespace App\Http\Controllers;

use App\Models\BusinessCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminBusinessCategoryController extends Controller
{
    public function index(): View
    {
        return view('admin.business-categories.index', [
            'categories' => BusinessCategory::withCount('listings')->orderBy('sort_order')->orderBy('name')->paginate(10)->withQueryString(),
            'categoriesCount' => BusinessCategory::count(),
            'activeCategoriesCount' => BusinessCategory::where('is_active', true)->count(),
            'types' => MarketplaceBusinessController::TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        BusinessCategory::create($this->data($request));

        return back()->with('success', 'Categoría creada correctamente.');
    }

    public function update(Request $request, BusinessCategory $category): RedirectResponse
    {
        $category->update($this->data($request, $category));

        return back()->with('success', 'Categoría actualizada.');
    }

    public function destroy(BusinessCategory $category): RedirectResponse
    {
        if ($category->listings()->exists()) {
            return back()->withErrors(['category' => 'No se puede eliminar una categoría utilizada. Desactívala para conservar el historial.']);
        }
        $category->delete();

        return back()->with('success', 'Categoría eliminada.');
    }

    private function data(Request $request, ?BusinessCategory $category = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'alpha_dash', 'max:60', Rule::unique('business_categories')->ignore($category)],
            'listing_type' => ['required', Rule::in(array_keys(MarketplaceBusinessController::TYPES))],
            'name' => ['required', 'string', 'max:100'],
            'icon' => ['nullable', 'string', 'max:12'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['required', 'integer', 'min:1', 'max:999'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
