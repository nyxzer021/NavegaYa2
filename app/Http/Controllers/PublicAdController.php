<?php

namespace App\Http\Controllers;

use App\Models\Advertisement;
use App\Models\SubscriptionPlan;
use App\Models\SystemSetting;
use App\Support\PhoneNumber;
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
            'supportNumber' => PhoneNumber::peru(SystemSetting::value('whatsapp_number')),
            'plans' => SubscriptionPlan::where('is_active', true)->orderBy('monthly_price')->get(),
        ]);
    }

    public function storeLead(Request $request): RedirectResponse
    {
        return $this->store($request);
    }
}
