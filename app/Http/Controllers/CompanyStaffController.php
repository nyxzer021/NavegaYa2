<?php

namespace App\Http\Controllers;

use App\Models\StaffInvitation;
use App\Models\User;
use App\Support\CompanyTeam;
use App\Support\DepartureSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CompanyStaffController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $invitation = CompanyTeam::invite($request);

        return back()
            ->with('success', __('staff.invited', ['email' => $invitation->email]))
            ->with('invite_url', route('staff.invitation', $invitation->token))
            ->with('invite_name', $invitation->name);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        CompanyTeam::change($request, $user);

        return back()->with('success', __('staff.profile_saved', ['name' => $user->name]));
    }

    public function toggle(Request $request, User $user): RedirectResponse
    {
        $organizationId = CompanyTeam::organization($request)->id;
        $membership = $user->organizations()->whereKey($organizationId)->first();
        $active = $membership?->pivot->status === 'active';
        if (! $active) {
            CompanyTeam::reactivate($request, $user);

            return back()->with('success', __('staff.reactivated', ['name' => $user->name]));
        }
        $drawer = CompanyTeam::openDrawer($user, $organizationId);
        if ($drawer !== null && ! $request->boolean('confirm')) {
            return back()
                ->with('open_cash_user', $user->id)
                ->withErrors(['confirm' => __('staff.open_cash', [
                    'name' => $user->name,
                    'site' => $drawer->site?->name ?: __('staff.no_site'),
                    'when' => DepartureSchedule::show($drawer->opened_at),
                ])]);
        }
        CompanyTeam::suspend($request, $user);

        return back()->with('success', __('staff.suspended', ['name' => $user->name]));
    }

    public function updateInvitation(Request $request, StaffInvitation $invitation): RedirectResponse
    {
        CompanyTeam::changeInvitation($request, $invitation);

        return back()->with('success', __('staff.profile_saved', ['name' => $invitation->name]));
    }

    public function resend(Request $request, StaffInvitation $invitation): RedirectResponse
    {
        $invitation = CompanyTeam::resend($request, $invitation);

        return back()
            ->with('success', __('staff.resent', ['email' => $invitation->email]))
            ->with('invite_url', route('staff.invitation', $invitation->token))
            ->with('invite_name', $invitation->name);
    }

    public function cancel(Request $request, StaffInvitation $invitation): RedirectResponse
    {
        CompanyTeam::cancel($request, $invitation);

        return back()->with('success', __('staff.cancelled', ['email' => $invitation->email]));
    }
}
