<?php

namespace App\Support;

use App\Models\CashSession;
use App\Models\Organization;
use App\Models\OrganizationSite;
use App\Models\Role;
use App\Models\StaffInvitation;
use App\Models\User;
use App\Notifications\StaffInvitationNotice;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CompanyTeam
{
    /**
     * @var list<string>
     */
    public const ROLES = [
        CompanyPermissions::ADMIN,
        CompanyPermissions::SELLER,
        CompanyPermissions::OPERATOR,
        CompanyPermissions::FINANCE,
    ];

    public static function invite(Request $request): StaffInvitation
    {
        $organization = self::organization($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150'],
            'profile' => ['required', Rule::in(CompanyPermissions::assignableProfiles())],
        ]);
        $site = $request->validate([
            'site_id' => self::siteRules($organization, $data['profile']),
        ]);
        $email = mb_strtolower(trim($data['email']));
        $existing = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if ($existing !== null && self::hasCompanyRole($existing)) {
            throw ValidationException::withMessages(['email' => __('staff.other_company')]);
        }
        $pending = StaffInvitation::query()
            ->where('organization_id', $organization->id)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('accepted_at')
            ->whereNull('cancelled_at')
            ->exists();
        if ($pending) {
            throw ValidationException::withMessages(['email' => __('staff.already_invited')]);
        }

        $invitation = StaffInvitation::query()->create([
            'organization_id' => $organization->id,
            'invited_by_user_id' => $request->user()->id,
            'name' => trim($data['name']),
            'email' => $email,
            'profile' => $data['profile'],
            'organization_site_id' => $site['site_id'] ?? null,
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
        ]);
        self::notify($invitation, $organization);
        AuditRecorder::record($request, 'staff.invited', $invitation, [
            'email' => $invitation->email,
            'name' => $invitation->name,
            'profile' => $invitation->profile,
            'site_id' => $invitation->organization_site_id,
        ]);

        return $invitation;
    }

    public static function accept(Request $request, StaffInvitation $invitation): User
    {
        return DB::transaction(function () use ($request, $invitation): User {
            $locked = StaffInvitation::query()->whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isOpen()) {
                throw ValidationException::withMessages(['email' => __('staff.link_closed')]);
            }
            $existing = User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($locked->email)])->first();
            if ($existing === null) {
                $data = $request->validate([
                    'password' => ['required', 'confirmed', 'min:8'],
                ]);
                $user = User::query()->create([
                    'name' => $locked->name,
                    'email' => $locked->email,
                    'password' => $data['password'],
                    'email_verified_at' => now(),
                    'organization_site_id' => $locked->organization_site_id,
                ]);
            } else {
                if (self::hasCompanyRole($existing)) {
                    throw ValidationException::withMessages(['email' => __('staff.other_company')]);
                }
                self::assertOwnsAccount($request, $existing);
                $existing->forceFill([
                    'email_verified_at' => $existing->email_verified_at ?? now(),
                    'organization_site_id' => $locked->organization_site_id,
                ])->save();
                $user = $existing;
            }
            self::assignRole($user, $locked->organization_id, $locked->profile, $locked->organization_site_id, true);
            $locked->update(['accepted_at' => now()]);

            return $user;
        });
    }

    /**
     * El enlace lo ve también el administrador: para unir una cuenta que ya existe,
     * quien acepta tiene que ser su dueño (sesión iniciada con ella o su contraseña).
     */
    private static function assertOwnsAccount(Request $request, User $existing): void
    {
        if ((int) $request->user()?->id === (int) $existing->id) {
            return;
        }
        $password = (string) $request->input('current_password', '');
        if ($password === '' || ! Hash::check($password, (string) $existing->password)) {
            throw ValidationException::withMessages(['current_password' => __('staff.prove_owner')]);
        }
    }

    public static function change(Request $request, User $member): void
    {
        $organization = self::organization($request);
        self::ownedMember($member, $organization->id);
        $data = $request->validate([
            'profile' => ['required', Rule::in(CompanyPermissions::assignableProfiles())],
        ]);
        $site = $request->validate([
            'site_id' => self::siteRules($organization, $data['profile']),
        ]);
        $current = self::memberProfile($member, $organization->id);
        if ($current === 'admin' && $data['profile'] !== 'admin' && self::activeAdmins($organization->id) <= 1) {
            throw ValidationException::withMessages(['profile' => __('staff.last_admin')]);
        }
        self::assignRole($member, $organization->id, $data['profile'], $site['site_id'] ?? null, false);
        AuditRecorder::record($request, 'staff.profile', $member, [
            'from' => $current,
            'to' => $data['profile'],
            'site_id' => $site['site_id'] ?? null,
        ]);
    }

    public static function changeInvitation(Request $request, StaffInvitation $invitation): void
    {
        $organization = self::organization($request);
        self::ownedInvitation($invitation, $organization->id);
        if ($invitation->accepted_at !== null || $invitation->cancelled_at !== null) {
            throw ValidationException::withMessages(['profile' => __('staff.link_closed')]);
        }
        $data = $request->validate([
            'profile' => ['required', Rule::in(CompanyPermissions::assignableProfiles())],
        ]);
        $site = $request->validate([
            'site_id' => self::siteRules($organization, $data['profile']),
        ]);
        $from = $invitation->profile;
        $invitation->update([
            'profile' => $data['profile'],
            'organization_site_id' => $site['site_id'] ?? null,
        ]);
        AuditRecorder::record($request, 'staff.profile', $invitation, [
            'email' => $invitation->email,
            'from' => $from,
            'to' => $data['profile'],
            'site_id' => $invitation->organization_site_id,
        ]);
    }

    public static function openDrawer(User $member, int $organizationId): ?CashSession
    {
        return CashSession::query()
            ->where('organization_id', $organizationId)
            ->where('user_id', $member->id)
            ->whereNull('closed_at')
            ->with('site')
            ->first();
    }

    public static function suspend(Request $request, User $member): void
    {
        $organization = self::organization($request);
        self::ownedMember($member, $organization->id);
        if (self::memberProfile($member, $organization->id) === 'admin' && self::activeAdmins($organization->id) <= 1) {
            throw ValidationException::withMessages(['status' => __('staff.last_admin')]);
        }
        $member->organizations()->updateExistingPivot($organization->id, ['status' => 'suspended']);
        self::closeSessions($member);
        AuditRecorder::record($request, 'staff.suspended', $member, [
            'profile' => self::memberProfile($member, $organization->id),
        ]);
    }

    public static function reactivate(Request $request, User $member): void
    {
        $organization = self::organization($request);
        self::ownedMember($member, $organization->id);
        $member->organizations()->updateExistingPivot($organization->id, ['status' => 'active']);
        AuditRecorder::record($request, 'staff.reactivated', $member, [
            'profile' => self::memberProfile($member, $organization->id),
        ]);
    }

    public static function resend(Request $request, StaffInvitation $invitation): StaffInvitation
    {
        $organization = self::organization($request);
        self::ownedInvitation($invitation, $organization->id);
        if ($invitation->accepted_at !== null || $invitation->cancelled_at !== null) {
            throw ValidationException::withMessages(['email' => __('staff.link_closed')]);
        }
        $invitation->update([
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
        ]);
        self::notify($invitation, $organization);
        AuditRecorder::record($request, 'staff.resent', $invitation, [
            'email' => $invitation->email,
            'profile' => $invitation->profile,
        ]);

        return $invitation->refresh();
    }

    public static function cancel(Request $request, StaffInvitation $invitation): void
    {
        $organization = self::organization($request);
        self::ownedInvitation($invitation, $organization->id);
        if ($invitation->accepted_at !== null) {
            throw ValidationException::withMessages(['email' => __('staff.link_closed')]);
        }
        $invitation->update(['cancelled_at' => now()]);
        AuditRecorder::record($request, 'staff.cancelled', $invitation, [
            'email' => $invitation->email,
            'profile' => $invitation->profile,
        ]);
    }

    public static function memberProfile(User $member, int $organizationId): ?string
    {
        $code = $member->roles()
            ->whereIn('code', self::ROLES)
            ->wherePivot('organization_id', $organizationId)
            ->value('roles.code');

        return is_string($code) ? CompanyPermissions::profileForRole($code) : null;
    }

    public static function activeAdmins(int $organizationId): int
    {
        return User::query()
            ->whereHas('organizations', fn ($query) => $query->where('organizations.id', $organizationId)->where('organization_user.status', 'active'))
            ->whereHas('roles', fn ($query) => $query->where('code', CompanyPermissions::ADMIN)->where('role_user.organization_id', $organizationId))
            ->count();
    }

    private static function assignRole(User $user, int $organizationId, string $profile, ?int $siteId, bool $activate): void
    {
        if ($activate) {
            $user->organizations()->syncWithoutDetaching([$organizationId => ['status' => 'active']]);
            $user->organizations()->updateExistingPivot($organizationId, ['status' => 'active']);
        }
        $roleIds = Role::query()->whereIn('code', self::ROLES)->pluck('id');
        $user->roles()->wherePivot('organization_id', $organizationId)->detach($roleIds);
        $role = Role::query()->where('code', CompanyPermissions::roleForProfile($profile))->firstOrFail();
        $user->roles()->attach($role->id, ['organization_id' => $organizationId]);
        $user->forceFill(['organization_site_id' => $siteId])->save();
    }

    private static function closeSessions(User $user): void
    {
        DB::table('sessions')->where('user_id', $user->id)->delete();
        $user->forceFill(['remember_token' => Str::random(60)])->save();
    }

    private static function notify(StaffInvitation $invitation, Organization $organization): void
    {
        $company = $organization->commercial_name ?: $organization->legal_name;
        try {
            Notification::route('mail', $invitation->email)->notify(new StaffInvitationNotice(
                route('staff.invitation', $invitation->token),
                $company,
            ));
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private static function hasCompanyRole(User $user): bool
    {
        return $user->roles()->whereIn('code', self::ROLES)->exists();
    }

    /**
     * @return list<ValidationRule|string>
     */
    private static function siteRules(Organization $organization, string $profile): array
    {
        $hasSites = OrganizationSite::query()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->exists();

        return [
            $profile === 'seller' && $hasSites ? 'required' : 'nullable',
            'integer',
            Rule::exists('organization_sites', 'id')->where(fn ($query) => $query->where('organization_id', $organization->id)->where('is_active', true)),
        ];
    }

    public static function organization(Request $request): Organization
    {
        return $request->user()->organizations()
            ->where('organizations.type', 'transport_company')
            ->where('organizations.status', 'active')
            ->wherePivot('status', 'active')
            ->firstOrFail();
    }

    private static function ownedMember(User $member, int $organizationId): void
    {
        abort_unless($member->agency_id === null, 404);
        abort_unless($member->organizations()->whereKey($organizationId)->exists(), 404);
        abort_unless(self::memberProfile($member, $organizationId) !== null, 404);
    }

    private static function ownedInvitation(StaffInvitation $invitation, int $organizationId): void
    {
        abort_unless((int) $invitation->organization_id === $organizationId, 404);
    }
}
