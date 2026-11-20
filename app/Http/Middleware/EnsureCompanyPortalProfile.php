<?php

namespace App\Http\Middleware;

use App\Support\CompanyPermissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyPortalProfile
{
    /**
     * El vendedor solo abre las pantallas con las que vende.
     * El administrador de la empresa no pasa por esta lista.
     *
     * @var list<string>
     */
    public const SELLER_ROUTES = CompanyPermissions::SELLER_ROUTES;

    /**
     * El vendedor de una agencia solo vende los pasajes de sus lotes.
     *
     * @var list<string>
     */
    public const AGENCY_ROUTES = CompanyPermissions::AGENCY_ROUTES;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $profile = $user === null ? null : CompanyPermissions::profile($user);
        if ($user === null || $profile === null || $profile === 'admin') {
            return $next($request);
        }

        $name = $request->route()?->getName();
        abort_unless(is_string($name) && CompanyPermissions::allows($user, $name), 403);

        return $next($request);
    }
}
