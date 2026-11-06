<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationSite;
use App\Support\AdminScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CompanySiteController extends Controller
{
    public function index(Request $request): View
    {
        $organization = $this->organization($request);

        return view('company.sites', [
            'organization' => $organization,
            'sites' => $organization->sites()->latest('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $organization = $this->organization($request);
        $organization->sites()->create($this->validated($request));

        return back()->with('success', 'Sede registrada.');
    }

    public function update(Request $request, OrganizationSite $site): RedirectResponse
    {
        $organization = $this->organization($request);
        abort_unless($site->organization_id === $organization->id, 404);
        abort_if($site->hidden_by_admin, 403);
        $site->update($this->validated($request));

        return back()->with('success', 'Sede actualizada.');
    }

    public function destroy(Request $request, OrganizationSite $site): RedirectResponse
    {
        $organization = $this->organization($request);
        abort_unless($site->organization_id === $organization->id, 404);
        $site->delete();

        return back()->with('success', 'Sede eliminada.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['required', Rule::in(['dock', 'office', 'airfield'])],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'hours' => ['nullable', 'string', 'max:120'],
            'receives_cargo' => ['nullable', 'boolean'],
            'show_on_where_to_buy' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'Indica el nombre de la sede.',
            'kind.required' => 'Elige el tipo de sede.',
            'address.required' => 'Indica la dirección.',
            'city.required' => 'Indica la ciudad.',
        ]);
        $data['receives_cargo'] = $request->boolean('receives_cargo');
        $data['show_on_where_to_buy'] = $request->boolean('show_on_where_to_buy');
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    private function organization(Request $request): Organization
    {
        abort_unless($request->user()->roles()->where('code', 'company_admin')->exists(), 403);

        return Organization::query()
            ->whereKey(AdminScope::organizationId($request->user()))
            ->where('type', 'transport_company')
            ->whereIn('status', ['active', 'pending'])
            ->firstOrFail();
    }
}
