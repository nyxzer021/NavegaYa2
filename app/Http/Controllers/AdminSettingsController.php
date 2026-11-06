<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminSettingsController
{
    public function index(Request $request): View
    {
        $tab = in_array($request->string('tab')->toString(), ['integrations', 'identity', 'operations', 'payments', 'appearance'], true) ? $request->string('tab')->toString() : 'integrations';

        return view('admin.settings.index', ['activeTab' => $tab, 'culqiConfigured' => filled(config('services.culqi.public_key')) && filled(config('services.culqi.secret_key')) && filled(config('services.culqi.webhook_secret')), 'whatsappNumber' => SystemSetting::value('whatsapp_number'), 'whatsappMessage' => SystemSetting::value('whatsapp_message', 'Hola, necesito ayuda para planificar mi viaje con NavegaYA.'), 'showWhatsApp' => SystemSetting::value('show_whatsapp_floating', '1') === '1', 'siteBrand' => SystemSetting::value('site_brand', 'NavegaYA'), 'footerDescription' => SystemSetting::value('footer_description', 'La plataforma para viajes fluviales, empresas verificadas y destinos amazónicos.'), 'footerRights' => SystemSetting::value('footer_rights', 'Todos los derechos reservados.'), 'sideBannerTitle' => SystemSetting::value('home_side_banner_title', 'Anuncie aquí'), 'sideBannerText' => SystemSetting::value('home_side_banner_text', 'Conozca nuestros planes'), 'sideBannerUrl' => SystemSetting::value('home_side_banner_url', '/anunciate'), 'weatherTemperature' => SystemSetting::value('weather_temperature', '28'), 'riverStatus' => SystemSetting::value('river_status', 'normal'), 'themeFont' => SystemSetting::value('theme_font', 'elegant'), 'themeForest' => SystemSetting::value('theme_forest', '#103a31'), 'themeGold' => SystemSetting::value('theme_gold', '#f2b624'), 'themeRiver' => SystemSetting::value('theme_river', '#32b6c9'), 'themeHeroHeight' => SystemSetting::value('theme_hero_height', '360'), 'platformFeePercent' => SystemSetting::value('platform_fee_percent', '10'), 'baggageRiver' => SystemSetting::value('baggage_allowance_river_kg', '50'), 'baggageAir' => SystemSetting::value('baggage_allowance_air_kg', '10'), 'parcelThreshold' => SystemSetting::value('parcel_weight_threshold_kg', '50')]);
    }

    public function updateWhatsApp(Request $request): RedirectResponse
    {
        $data = $request->validate(['whatsapp_number' => ['nullable', 'string', 'max:30'], 'whatsapp_message' => ['required', 'string', 'max:500'], 'show_whatsapp_floating' => ['nullable', 'boolean']]);
        $data['whatsapp_number'] = PhoneNumber::peru($data['whatsapp_number'] ?? null) ?? '';
        $data['show_whatsapp_floating'] = $request->boolean('show_whatsapp_floating') ? '1' : '0';
        foreach ($data as $key => $value) {
            SystemSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        return back()->with('success', 'WhatsApp público actualizado.');
    }

    public function updateChrome(Request $request): RedirectResponse
    {
        $data = $request->validate(['site_brand' => ['required', 'string', 'max:40'], 'footer_description' => ['required', 'string', 'max:300'], 'footer_rights' => ['required', 'string', 'max:120'], 'home_side_banner_title' => ['nullable', 'string', 'max:60'], 'home_side_banner_text' => ['nullable', 'string', 'max:120'], 'home_side_banner_url' => ['nullable', 'string', 'max:500']]);
        foreach (array_filter($data, fn ($value) => $value !== null) as $key => $value) {
            SystemSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        return back()->with('success', 'Encabezado y pie de página actualizados.');
    }

    public function updatePayments(Request $request): RedirectResponse
    {
        $data = $request->validate(['platform_fee_percent' => ['required', 'numeric', 'min:0', 'max:30']]);
        SystemSetting::updateOrCreate(['key' => 'platform_fee_percent'], ['value' => number_format((float) $data['platform_fee_percent'], 2, '.', '')]);

        return back()->with('success', 'Comisión de NavegaYA actualizada.');
    }

    public function updateOperations(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'weather_temperature' => ['required', 'integer', 'min:0', 'max:50'],
            'river_status' => ['required', 'in:normal,rising,high,falling,low'],
        ]);

        foreach ($data as $key => $value) {
            SystemSetting::updateOrCreate(['key' => $key], ['value' => (string) $value]);
        }

        return back()->with('success', 'Información operativa pública actualizada.');
    }

    public function updateThresholds(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'baggage_allowance_river_kg' => ['required', 'integer', 'min:0', 'max:200'],
            'baggage_allowance_air_kg' => ['required', 'integer', 'min:0', 'max:100'],
            'parcel_weight_threshold_kg' => ['required', 'integer', 'min:0', 'max:500'],
            'baggage_hand_kg' => ['nullable', 'integer', 'min:0', 'max:100'],
            'baggage_hold_kg' => ['nullable', 'integer', 'min:0', 'max:200'],
        ], [
            'baggage_allowance_river_kg.required' => 'Indica el equipaje fluvial.',
            'baggage_allowance_air_kg.required' => 'Indica el equipaje aéreo.',
            'parcel_weight_threshold_kg.required' => 'Indica el umbral de encomienda.',
        ]);
        foreach ($data as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            SystemSetting::updateOrCreate(['key' => $key], ['value' => (string) $value]);
        }

        return back()->with('success', 'Umbrales guardados. El resumen público usa estos valores cuando la empresa no definió los suyos.');
    }

    public function updateTheme(Request $request): RedirectResponse
    {
        $data = $request->validate(['theme_font' => ['required', 'in:elegant,modern'], 'theme_forest' => ['required', 'regex:/^#[A-Fa-f0-9]{6}$/'], 'theme_gold' => ['required', 'regex:/^#[A-Fa-f0-9]{6}$/'], 'theme_river' => ['required', 'regex:/^#[A-Fa-f0-9]{6}$/'], 'theme_hero_height' => ['required', 'integer', 'min:280', 'max:520']]);
        foreach ($data as $key => $value) {
            SystemSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        return back()->with('success', 'Diseño público actualizado.');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['platform_fee_percent' => ['nullable', 'numeric', 'min:0', 'max:30'], 'site_brand' => ['nullable', 'string', 'max:40'], 'whatsapp_number' => ['nullable', 'regex:/^[1-9][0-9]{7,14}$/']]);
        foreach (array_filter($data, fn ($value) => $value !== null) as $key => $value) {
            SystemSetting::updateOrCreate(['key' => $key], ['value' => (string) $value]);
        }

        return back()->with('success', 'Configuración comercial actualizada.');
    }
}
