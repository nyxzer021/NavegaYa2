<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminSettingsController
{
    public function index(): View
    {
        return view('admin.settings.index', ['whatsappNumber' => SystemSetting::value('whatsapp_number'), 'whatsappMessage' => SystemSetting::value('whatsapp_message', 'Hola, necesito ayuda para planificar mi viaje con NavegaYA.'), 'siteBrand' => SystemSetting::value('site_brand', 'NavegaYA'), 'footerDescription' => SystemSetting::value('footer_description', 'La plataforma para viajes fluviales, empresas verificadas y destinos amazónicos.'), 'footerRights' => SystemSetting::value('footer_rights', 'Todos los derechos reservados.'), 'themeFont' => SystemSetting::value('theme_font', 'elegant'), 'themeForest' => SystemSetting::value('theme_forest', '#103a31'), 'themeGold' => SystemSetting::value('theme_gold', '#f2b624'), 'themeRiver' => SystemSetting::value('theme_river', '#32b6c9'), 'themeHeroHeight' => SystemSetting::value('theme_hero_height', '360'), 'platformFeePercent' => SystemSetting::value('platform_fee_percent', '10')]);
    }

    public function updateWhatsApp(Request $request): RedirectResponse
    {
        $data = $request->validate(['whatsapp_number' => ['required', 'regex:/^[1-9][0-9]{7,14}$/'], 'whatsapp_message' => ['required', 'string', 'max:500']]);
        foreach ($data as $key => $value) {
            SystemSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        return back()->with('success', 'WhatsApp público actualizado.');
    }

    public function updateChrome(Request $request): RedirectResponse
    {
        $data = $request->validate(['site_brand' => ['required', 'string', 'max:40'], 'footer_description' => ['required', 'string', 'max:300'], 'footer_rights' => ['required', 'string', 'max:120']]);
        foreach ($data as $key => $value) {
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
