<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\OrganizationPhoto;
use App\Support\AdminScope;
use App\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CompanyPublicProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $organization = $this->organization($request)->load('photos');

        return view('company.public-profile', ['organization' => $organization]);
    }

    public function update(Request $request): RedirectResponse
    {
        $organization = $this->organization($request);
        $data = $request->validate([
            'commercial_name' => ['nullable', 'string', 'max:150'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'whatsapp_message' => ['nullable', 'string', 'max:500'],
            'service_hours' => ['nullable', 'string', 'max:180'],
            'show_public_whatsapp' => ['nullable', 'boolean'],
            'phone' => ['nullable', 'string', 'max:30'],
            'website' => ['nullable', 'url', 'max:500'],
            'address' => ['nullable', 'string', 'max:250'],
            'public_description' => ['nullable', 'string', 'max:1500'],
            'logo' => ['nullable', 'image', 'max:5120'],
            'cover' => ['nullable', 'image', 'max:5120'],
        ]);
        $data['whatsapp'] = PhoneNumber::peru($data['whatsapp'] ?? null);
        $data['show_public_whatsapp'] = $request->boolean('show_public_whatsapp');
        $data['profile_content_status'] = 'published';
        if ($request->hasFile('logo')) {
            $data['logo_path'] = Storage::url($request->file('logo')->store('company-profiles', 'public'));
        }
        if ($request->hasFile('cover')) {
            $data['cover_image_path'] = Storage::url($request->file('cover')->store('company-profiles', 'public'));
        }
        unset($data['logo'], $data['cover']);
        $organization->update($data);

        return back()->with('success', 'La ficha pública quedó actualizada.');
    }

    public function storePhotos(Request $request): RedirectResponse
    {
        $organization = $this->organization($request);
        $data = $request->validate([
            'photos' => ['required', 'array', 'max:8'],
            'photos.*' => ['image', 'max:5120'],
            'caption' => ['nullable', 'string', 'max:120'],
        ]);
        $next = (int) $organization->photos()->max('sort_order');
        foreach ($data['photos'] as $photo) {
            $next++;
            $organization->photos()->create([
                'path' => Storage::url($photo->store('company-profiles', 'public')),
                'caption' => $data['caption'] ?? null,
                'sort_order' => $next,
            ]);
        }
        $organization->update(['profile_content_status' => 'published']);

        return back()->with('success', 'Las fotos ya aparecen en la ficha pública.');
    }

    public function destroyPhoto(Request $request, OrganizationPhoto $photo): RedirectResponse
    {
        $organization = $this->organization($request);
        abort_unless((int) $photo->organization_id === (int) $organization->id, 404);
        $photo->delete();

        return back()->with('success', 'La foto se quitó de la galería.');
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
