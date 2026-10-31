<?php

namespace App\Http\Controllers;

use App\Models\CompanyReview;
use App\Models\Organization;
use App\Support\AdminScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AdminOrganizationDirectoryController extends Controller
{
    public function index(): View
    {
        $organizations = Organization::query()
            ->withCount('users')
            ->when(AdminScope::organizationId(auth()->user()), fn ($query, $id) => $query->whereKey($id))
            ->orderByRaw("case status when 'pending' then 0 when 'active' then 1 else 2 end")
            ->orderBy('legal_name')
            ->paginate(12);

        return view('admin.organization-directory', compact('organizations'));
    }

    public function show(Organization $organization): View
    {
        $this->authorizeOrganization($organization);
        $organization->load(['users.roles', 'reviews.user']);

        return view('admin.organization-show', compact('organization'));
    }

    public function approveReview(Organization $organization, CompanyReview $review): RedirectResponse
    {
        $this->authorizeOrganization($organization);
        abort_unless($review->organization_id === $organization->id, 404);
        $review->update(['status' => 'published']);

        return back()->with('success', 'Valoración publicada.');
    }

    public function updatePublicProfile(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorizeOrganization($organization);
        $data = $request->validate(['commercial_name' => ['nullable', 'string', 'max:150'], 'whatsapp' => ['nullable', 'string', 'max:30'], 'phone' => ['nullable', 'string', 'max:30'], 'website' => ['nullable', 'url', 'max:500'], 'address' => ['nullable', 'string', 'max:250'], 'public_description' => ['nullable', 'string', 'max:1500'], 'logo' => ['nullable', 'image', 'max:5120'], 'cover' => ['nullable', 'image', 'max:5120']]);
        if ($request->hasFile('logo')) {
            $data['logo_path'] = Storage::url($request->file('logo')->store('company-profiles', 'public'));
        }
        if ($request->hasFile('cover')) {
            $data['cover_image_path'] = Storage::url($request->file('cover')->store('company-profiles', 'public'));
        }
        unset($data['logo'],$data['cover']);
        $organization->update($data);

        return back()->with('success', 'Perfil público actualizado.');
    }

    private function authorizeOrganization(Organization $organization): void
    {
        if ($organizationId = AdminScope::organizationId(auth()->user())) {
            abort_unless($organization->id === $organizationId, 403);
        }
    }
}
