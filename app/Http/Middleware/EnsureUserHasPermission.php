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
        'admin.organizations.' => 'companies', 'admin.organization-directory.' => 'companies', 'admin.operation.' => 'routes',
        'admin.ports.' => 'ports', 'admin.transport-routes.' => 'routes', 'admin.vessels.' => 'fleet', 'admin.aircraft.' => 'fleet',
        'admin.manifests.' => 'boarding',
        'admin.cargo.' => 'cargo', 'admin.boarding.' => 'boarding', 'admin.reports.' => 'reports', 'admin.ads.' => 'ads',
        'admin.destinations.' => 'ads',
        'admin.audit.' => 'audit', 'admin.users.' => 'users',
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
