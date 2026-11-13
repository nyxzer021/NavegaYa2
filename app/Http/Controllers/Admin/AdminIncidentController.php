<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\CompanyIncidentController;
use App\Http\Controllers\Controller;
use App\Models\CrewMember;
use App\Models\DepartureIncident;
use App\Models\IncidentPhoto;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminIncidentController extends Controller
{
    public function index(): View
    {
        return view('admin.incidents.index', [
            'incidents' => DepartureIncident::query()
                ->with(['organization', 'routeDeparture.transportRoute.originPort', 'routeDeparture.transportRoute.destinationPort', 'photos'])
                ->latest()
                ->limit(50)
                ->get(),
            'expiredLicenses' => CrewMember::query()
                ->with('organization')
                ->whereNotNull('license_expires_on')
                ->whereDate('license_expires_on', '<', now()->toDateString())
                ->orderBy('license_expires_on')
                ->get(),
        ]);
    }

    public function photo(IncidentPhoto $photo): StreamedResponse
    {
        $photo->load('incident');
        CompanyIncidentController::authorizePhoto($photo);

        return Storage::disk('local')->response($photo->path, $photo->original_name ?: 'foto');
    }
}
