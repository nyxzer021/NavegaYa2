<?php

namespace App\Http\Controllers;

use App\Models\StaffInvitation;
use App\Models\User;
use App\Support\AuditRecorder;
use App\Support\CompanyPermissions;
use App\Support\CompanyTeam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class StaffInvitationController extends Controller
{
    public function create(string $token): View|Response
    {
        $invitation = StaffInvitation::query()->where('token', $token)->firstOrFail();
        if (! $invitation->isOpen()) {
            return response()->view('auth.staff-invitation-closed', ['invitation' => $invitation], 410);
        }
        $invitation->load('organization');
        $existing = User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($invitation->email)])->first();

        return view('auth.staff-invitation', [
            'invitation' => $invitation,
            'existing' => $existing,
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse|Response
    {
        $invitation = StaffInvitation::query()->where('token', $token)->firstOrFail();
        if (! $invitation->isOpen()) {
            return response()->view('auth.staff-invitation-closed', ['invitation' => $invitation], 410);
        }
        $user = CompanyTeam::accept($request, $invitation);
        Auth::login($user);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        AuditRecorder::record($request, 'staff.accepted', $user, [
            'email' => $invitation->email,
            'profile' => $invitation->profile,
        ]);
        $home = match (CompanyPermissions::profile($user)) {
            'operator' => 'company.departures.index',
            'finance' => 'company.sales.index',
            'admin' => 'company.dashboard',
            default => 'company.pos.index',
        };

        return redirect()->route($home);
    }
}
