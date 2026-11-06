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
        'company.parcels.index',
        'company.freight.index',
        'company.sales.index',
        'company.sales.export',
        'company.cash-close.index',
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

        $name = $request->route()?->getName();
        abort_unless(is_string($name) && in_array($name, self::SELLER_ROUTES, true), 403);

        return $next($request);
    }
}
