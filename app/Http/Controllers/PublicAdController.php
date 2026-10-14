<?php

namespace App\Http\Controllers;

use App\Models\Advertisement;
use App\Models\SystemSetting;
use App\Models\SubscriptionPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicAdController extends AdvertisingController
{
    public function showLanding(): View
    {
        return view('public.advertise', [
            'businessTypes' => Advertisement::BUSINESS_TYPES,
            'placements' => Advertisement::PLACEMENTS,
            'supportNumber' => preg_replace('/\D+/', '', (string) SystemSetting::value('whatsapp_number', '51900000000')),
            'plans' => SubscriptionPlan::where('is_active', true)->orderBy('monthly_price')->get(),
        ]);
    }

    public function storeLead(Request $request): RedirectResponse
    {
        return $this->store($request);
    }
}
