<?php

namespace App\Http\Controllers;

use App\Models\Advertisement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdvertisingController extends Controller
{
    public function create(): View
    {
        return view('advertising.create', ['businessTypes' => Advertisement::BUSINESS_TYPES, 'placements' => Advertisement::PLACEMENTS]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:150'],
            'business_type' => ['required', 'in:'.implode(',', array_keys(Advertisement::BUSINESS_TYPES))],
            'contact_name' => ['required', 'string', 'max:150'],
            'phone_whatsapp' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'city_destination' => ['required', 'string', 'max:100'],
            'ruc' => ['nullable', 'digits:11'],
            'placements' => ['required', 'array', 'min:1'],
            'placements.*' => ['in:'.implode(',', array_keys(Advertisement::PLACEMENTS))],
            'target_url' => ['nullable', 'url', 'max:500'],
            'banner_image' => ['nullable', 'image', 'max:4096'],
        ]);

        $data['banner_image_path'] = $request->file('banner_image')?->store('advertisements', 'public');
        $data['category'] = Advertisement::BUSINESS_TYPES[$data['business_type']];
        $data['title'] = $data['business_name'];
        $data['status'] = 'lead_pending';
        $data['monthly_fee'] = 300;
        Advertisement::create($data);

        $message = '¡Solicitud recibida con éxito! Nuestro equipo comercial se comunicará a tu WhatsApp en menos de 24 horas.';

        return redirect()->route('advertising.create')->with([
            'success' => $message,
            'success_lead' => $message,
        ]);
    }
}
