<?php

namespace App\Http\Middleware;

use App\Models\CargoShipment;
use App\Models\Organization;
use App\Models\RouteDeparture;
use App\Models\Ticket;
use App\Models\TransportRoute;
use App\Models\Vessel;
use App\Support\AdminScope;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    private const ROUTE_PERMISSIONS = [
        'admin.requests.' => 'companies', 'admin.support.' => 'companies', 'admin.companies.' => 'companies',
        'admin.organizations.' => 'companies', 'admin.organization-directory.' => 'companies', 'admin.fleet-compliance.' => 'fleet',
        'admin.operation.' => 'routes', 'admin.itineraries.' => 'routes', 'admin.supervision.' => 'routes',
        'admin.ports.' => 'ports', 'admin.master-routes.' => 'routes', 'admin.transport-routes.' => 'routes',
        'admin.air-routes.' => 'routes', 'admin.vessels.' => 'fleet', 'admin.aircraft.' => 'fleet', 'admin.fleet.' => 'fleet',
        'admin.manifests.' => 'boarding',
        'admin.cargo.' => 'cargo', 'admin.boarding.' => 'boarding', 'admin.reports.' => 'reports',
        'admin.sales.' => 'reports', 'admin.commissions.' => 'reports', 'admin.settlements.' => 'reports', 'admin.refunds.' => 'reports',
        'admin.ads.' => 'ads', 'admin.advertisements.' => 'ads', 'admin.advertising-subscriptions.' => 'ads',
        'admin.destinations.' => 'ads', 'admin.destination-listings.' => 'ads', 'admin.business-categories.' => 'ads',
        'admin.tours.' => 'ads', 'admin.tourism-partners.' => 'ads',
        'admin.moderation.' => 'audit', 'admin.audit.' => 'audit', 'admin.users.' => 'users',
        'admin.settings.' => 'settings',
        'admin.home-hero.' => 'settings',
        'admin.navigation.' => 'settings',
    ];

    public function handle(Request $request, Closure $next, ?string $permission = null): Response
    {
        $user = $request->user();
        abort_unless($user, 403);
        if ($user->roles()->where('code', 'super_admin')->exists()) {
            abort_if($request->routeIs('admin.boarding.*'), 403, 'El embarque corresponde al operador de transporte.');

            return $next($request);
        }
        if ($request->routeIs('admin.dashboard')) {
            abort_unless($user->permissions()->exists(), 403);

            return $next($request);
        }
        $required = $permission;
        if (! $required && $request->route()?->getName()) {
            foreach (self::ROUTE_PERMISSIONS as $prefix => $code) {
                if (str_starts_with($request->route()->getName(), $prefix)) {
                    $required = $code;
                    break;
                }
            }
        }
        abort_unless($required && $user->hasPermission($required), 403);
        $organizationId = AdminScope::organizationId($user);
        if ($organizationId) {
            foreach ($request->route()->parameters() as $model) {
                $belongsToOrganization = match (true) {
                    $model instanceof Vessel => $model->organization_id === $organizationId,
                    $model instanceof Organization => $model->id === $organizationId,
                    $model instanceof TransportRoute => $model->organization_id === $organizationId,
                    $model instanceof RouteDeparture => $model->transportRoute?->organization_id === $organizationId,
                    $model instanceof CargoShipment => ($model->departure?->transportRoute?->organization_id ?? $model->airDeparture?->airRoute?->organization_id) === $organizationId,
                    $model instanceof Ticket => ($model->reservationSeat?->reservation?->departure?->transportRoute?->organization_id ?? $model->reservationSeat?->reservation?->airDeparture?->airRoute?->organization_id) === $organizationId,
                    default => true,
                };
                abort_unless($belongsToOrganization, 403);
            }
        }

        return $next($request);
    }
}
