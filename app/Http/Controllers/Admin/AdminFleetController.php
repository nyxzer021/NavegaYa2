<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Aircraft;
use App\Models\Vessel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminFleetController extends Controller
{
    public function updateVesselStatus(Request $request, Vessel $vessel): RedirectResponse
    {
        $vessel->update($request->validate(['status' => ['required', Rule::in(['ready', 'maintenance', 'inactive'])]]));

        return back()->with('success', 'Estado operativo de la embarcación actualizado.');
    }

    public function updateAircraftStatus(Request $request, Aircraft $aircraft): RedirectResponse
    {
        $aircraft->update($request->validate(['status' => ['required', Rule::in(['ready', 'maintenance', 'inactive'])]]));

        return back()->with('success', 'Estado operativo de la aeronave actualizado.');
    }
}
