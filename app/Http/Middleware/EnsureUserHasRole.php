<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $roles = preg_split('/[|,]/', $role, -1, PREG_SPLIT_NO_EMPTY);
        $user = $request->user();
        abort_unless($user && $user->roles()->whereIn('code', $roles)->exists(), 403);
        if (array_intersect($roles, ['company_admin', 'company_counter'])) {
            $isCompanyAdmin = $user->roles()->where('code', 'company_admin')->exists();
            $statuses = $isCompanyAdmin ? ['active', 'pending'] : ['active'];
            abort_unless($user->organizations()->whereIn('organization_user.status', $statuses)->whereIn('organizations.status', $statuses)->exists(), 403);
        }

        return $next($request);
    }
}
