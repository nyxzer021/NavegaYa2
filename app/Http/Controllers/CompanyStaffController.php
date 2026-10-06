<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CompanyStaffController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $organization = $this->organization();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'document_number' => ['required', 'string', 'max:40'],
            'phone' => ['required', 'string', 'max:40'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        DB::transaction(function () use ($data, $organization): void {
            $user = User::create(array_merge($data, ['password' => Hash::make($data['password']), 'email_verified_at' => now()]));
            $user->organizations()->attach($organization->id, ['status' => 'active']);
            $user->roles()->attach(Role::where('code', 'company_counter')->firstOrFail()->id, ['organization_id' => $organization->id]);
        });

        return back()->with('success', 'Usuario Counter creado y vinculado a tu empresa.');
    }

    public function toggle(User $user): RedirectResponse
    {
        $organization = $this->organization();
        $this->authorizeCounter($user, $organization->id);
        $membership = $user->organizations()->whereKey($organization->id)->firstOrFail();
        $next = $membership->pivot->status === 'active' ? 'inactive' : 'active';
        $user->organizations()->updateExistingPivot($organization->id, ['status' => $next]);

        return back()->with('success', $next === 'active' ? 'Usuario Counter habilitado.' : 'Usuario Counter deshabilitado.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $organization = $this->organization();
        $this->authorizeCounter($user, $organization->id);
        DB::transaction(function () use ($user, $organization): void {
            $user->roles()->wherePivot('organization_id', $organization->id)->detach();
            $user->organizations()->detach($organization->id);
            if ($user->organizations()->doesntExist() && $user->roles()->doesntExist()) {
                $user->delete();
            }
        });

        return back()->with('success', 'Usuario Counter retirado de la empresa.');
    }

    private function authorizeCounter(User $user, int $organizationId): void
    {
        abort_unless($user->organizations()->whereKey($organizationId)->exists()
            && $user->roles()->where('code', 'company_counter')->wherePivot('organization_id', $organizationId)->exists(), 404);
    }

    private function organization()
    {
        return auth()->user()->organizations()->wherePivot('status', 'active')->where('organizations.status', 'active')->firstOrFail();
    }
}
