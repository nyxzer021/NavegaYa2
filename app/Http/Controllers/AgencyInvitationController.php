<?php

namespace App\Http\Controllers;

use App\Models\AgencyInvitation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AgencyInvitationController extends Controller
{
    public function create(string $token): View
    {
        $invitation = $this->invitation($token);

        return view('agency-invitation', compact('invitation'));
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->invitation($token);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);
        $agency = $invitation->agency;
        $user = User::query()->where('email', $invitation->email)->first();
        if ($user && $user->agency_id && (int) $user->agency_id !== (int) $agency->id) {
            return back()->withErrors(['email' => __('agencies.invite_taken')]);
        }
        if ($user === null) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $invitation->email,
                'password' => Hash::make($data['password']),
                'email_verified_at' => now(),
            ]);
        } else {
            $user->forceFill([
                'name' => $data['name'],
                'password' => Hash::make($data['password']),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
        }
        $user->forceFill(['agency_id' => $agency->id])->save();
        $user->organizations()->syncWithoutDetaching([$agency->organization_id => ['status' => 'active']]);
        Role::query()->where('code', 'company_counter')->firstOrFail()->users()->syncWithoutDetaching([
            $user->id => ['organization_id' => $agency->organization_id],
        ]);
        $invitation->update(['accepted_at' => now()]);
        Auth::login($user);

        return redirect()->route('company.pos.index');
    }

    private function invitation(string $token): AgencyInvitation
    {
        return AgencyInvitation::query()
            ->with('agency')
            ->where('token', $token)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->firstOrFail();
    }
}
