<?php

namespace App\Http\Controllers;

use App\Models\OrganizationInvitation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class CompanyActivationController extends Controller
{
    public function create(string $token)
    {
        $invitation = OrganizationInvitation::where('token', $token)->whereNull('accepted_at')->where('expires_at', '>', now())->firstOrFail();

        return view('company-activation', compact('invitation'));
    }

    public function store(Request $request, string $token)
    {
        $invitation = OrganizationInvitation::where('token', $token)->whereNull('accepted_at')->where('expires_at', '>', now())->firstOrFail();
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'password' => ['required', 'confirmed', 'min:8']]);
        $user = User::firstOrCreate(['email' => $invitation->email], ['name' => $data['name'], 'password' => Hash::make($data['password']), 'email_verified_at' => now()]);
        $user->organizations()->syncWithoutDetaching([$invitation->organization_id => ['status' => 'active']]);
        Role::where('code', 'company_admin')->firstOrFail()->users()->syncWithoutDetaching([$user->id => ['organization_id' => $invitation->organization_id]]);
        $invitation->update(['accepted_at' => now()]);
        Auth::login($user);

        return redirect()->route('company.dashboard');
    }
}
