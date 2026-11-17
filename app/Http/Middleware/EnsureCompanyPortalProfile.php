<?php

namespace App\Http\Middleware;

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
    public const SELLER_ROUTES = [
        'company.pos.index',
        'company.pos.store',
        'company.pos.void',
        'company.parcels.index',
        'company.parcels.store',
        'company.parcels.assign',
        'company.parcels.board',
        'company.parcels.deliver',
        'company.parcels.guide',
        'company.freight.index',
        'company.sales.index',
        'company.sales.export',
        'company.cash-close.index',
        'company.cash-close.open',
        'company.cash-close.close',
        'company.cash-close.print',
        'company.cash-close.export',
    ];

    /**
     * El vendedor de una agencia solo vende los pasajes de sus lotes.
     *
     * @var list<string>
     */
    public const AGENCY_ROUTES = [
        'company.pos.index',
        'company.pos.store',
        'company.pos.void',
        'company.sales.index',
        'company.sales.export',
        'company.agency-lots.index',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null || $user->roles()->where('code', 'company_admin')->exists()) {
            return $next($request);
        }

        if (! $user->roles()->where('code', 'company_counter')->exists()) {
            return $next($request);
        }

        $allowed = $user->agency_id ? self::AGENCY_ROUTES : self::SELLER_ROUTES;
        $name = $request->route()?->getName();
        abort_unless(is_string($name) && in_array($name, $allowed, true), 403);

        return $next($request);
    }
}
