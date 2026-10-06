<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CompanyPublicProfileController extends Controller
{
    private function organization(Request $request)
    {
        return $request->user()->organizations()->where('type', 'transport_company')->firstOrFail();
    }

    public function edit(Request $request): View
    {
        return view('company.public-profile', ['organization' => $this->organization($request)]);
    }

    public function update(Request $request): RedirectResponse
    {
        $organization = $this->organization($request);
        $data = $request->validate(['commercial_name' => ['nullable', 'string', 'max:150'], 'whatsapp' => ['nullable', 'string', 'max:30'], 'phone' => ['nullable', 'string', 'max:30'], 'website' => ['nullable', 'url', 'max:500'], 'address' => ['nullable', 'string', 'max:250'], 'public_description' => ['nullable', 'string', 'max:1500'], 'logo' => ['nullable', 'image', 'max:5120'], 'cover' => ['nullable', 'image', 'max:5120']]);
        if ($request->hasFile('logo')) {
            $data['logo_path'] = Storage::url($request->file('logo')->store('company-profiles', 'public'));
        }if ($request->hasFile('cover')) {
            $data['cover_image_path'] = Storage::url($request->file('cover')->store('company-profiles', 'public'));
        }unset($data['logo'],$data['cover']);
        $organization->update($data);

        return back()->with('success', 'Perfil público actualizado.');
    }
}
