<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Una sola lista dice qué módulos y qué rutas abre cada perfil.
 * El menú y el middleware leen de aquí.
 */
class CompanyPermissions
{
    public const ADMIN = 'company_admin';

    public const SELLER = 'company_counter';

    public const OPERATOR = 'company_operator';

    public const FINANCE = 'company_finance';

    /**
     * El vendedor de muelle sigue con exactamente estas rutas.
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
     * El vendedor de agencia sigue con exactamente estas rutas.
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

    /**
     * Finanzas mira la caja y no la opera.
     *
     * @var list<string>
     */
    public const CASH_WRITE_ROUTES = [
        'company.cash-close.open',
        'company.cash-close.close',
    ];

    /**
     * @var list<string>
     */
    public const PUBLIC_ROUTES = [
        'company.registration',
        'company.registration.store',
        'company.register',
        'company.registration.verify',
        'company.activation',
    ];

    /**
     * @return list<string>
     */
    public static function assignableProfiles(): array
    {
        return ['admin', 'seller', 'operator', 'finance'];
    }

    public static function roleForProfile(string $profile): string
    {
        return match ($profile) {
            'admin' => self::ADMIN,
            'operator' => self::OPERATOR,
            'finance' => self::FINANCE,
            default => self::SELLER,
        };
    }

    public static function profileForRole(string $code): ?string
    {
        return match ($code) {
            self::ADMIN => 'admin',
            self::OPERATOR => 'operator',
            self::FINANCE => 'finance',
            self::SELLER => 'seller',
            default => null,
        };
    }

    public static function profile(User $user): ?string
    {
        $organizationId = AdminScope::organizationId($user);
        $codes = $user->roles()
            ->when($organizationId !== null, fn ($query) => $query->where('role_user.organization_id', $organizationId))
            ->pluck('code');
        if ($codes->contains(self::ADMIN)) {
            return 'admin';
        }
        if ($codes->contains(self::SELLER) && $user->agency_id) {
            return 'agency';
        }
        if ($codes->contains(self::FINANCE)) {
            return 'finance';
        }
        if ($codes->contains(self::OPERATOR)) {
            return 'operator';
        }
        if ($codes->contains(self::SELLER)) {
            return 'seller';
        }

        return null;
    }

    public static function viewsAllCash(User $user): bool
    {
        return in_array(self::profile($user), ['admin', 'finance'], true);
    }

    public static function operatesCash(User $user): bool
    {
        return in_array(self::profile($user), ['admin', 'seller'], true);
    }

    public static function allows(User $user, ?string $routeName): bool
    {
        $profile = self::profile($user);
        if ($profile === null || $routeName === null) {
            return false;
        }

        return self::decision($profile, $routeName) === true;
    }

    /**
     * null = la ruta company.* todavía no está clasificada.
     */
    public static function decision(string $profile, string $routeName): ?bool
    {
        if (! str_starts_with($routeName, 'company.')) {
            return null;
        }
        if (in_array($routeName, self::PUBLIC_ROUTES, true)) {
            return false;
        }
        if (! self::isClassified($routeName)) {
            return null;
        }
        if ($profile === 'admin') {
            $onlyForAgencySeller = self::granted('agency', $routeName) && ! self::granted('seller', $routeName);

            return ! $onlyForAgencySeller;
        }
        if (! in_array($profile, ['seller', 'agency', 'operator', 'finance'], true)) {
            return null;
        }

        return self::granted($profile, $routeName);
    }

    public static function isPortalRoute(string $routeName): bool
    {
        return str_starts_with($routeName, 'company.')
            && ! in_array($routeName, self::PUBLIC_ROUTES, true);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function menuKeys(): array
    {
        return [
            'admin' => ['*'],
            'seller' => ['pos', 'parcels', 'sales', 'cash_close'],
            'agency' => ['pos', 'sales', 'lots'],
            'operator' => ['departures', 'boarding', 'fleet', 'crew', 'incidents'],
            'finance' => ['sales', 'collections', 'refunds', 'claims', 'agency_commissions', 'receipts', 'reports', 'cash_close', 'audit'],
        ];
    }

    /**
     * @return list<array{group: string|null, key: string, route: string, patterns: list<string>, needs_active: bool, visible: bool}>
     */
    public static function modules(): array
    {
        return [
            self::module(null, 'home', 'company.dashboard', ['company.dashboard', 'company.dashboard.*'], false),
            self::module('company', 'company_details', 'company.details.index', ['company.details.*', 'company.modalities.*'], false),
            self::module('company', 'branches', 'company.branches.index', ['company.branches.*', 'company.sites.*'], false),
            self::module('company', 'online_payments', 'company.online-payments.index', ['company.online-payments.*'], false),
            self::module('operation', 'fleet', 'company.fleet.index', ['company.fleet.*', 'company.vessels.*', 'company.aircraft.*'], true),
            self::module('operation', 'routes', 'company.routes.index', ['company.routes.*'], true),
            self::module('operation', 'departures', 'company.departures.index', [
                'company.departures.index', 'company.departures.create', 'company.departures.edit',
                'company.departures.store', 'company.departures.update', 'company.departures.status',
                'company.departures.contingency', 'company.departures.series.*',
            ], true),
            self::module('operation', 'fares', 'company.fares.index', ['company.fares.*', 'company.discounts.*'], true),
            self::module('operation', 'boarding', 'company.counter.index', ['company.counter.*', 'company.boarding.*', 'company.departures.manifest'], true),
            self::module('operation', 'crew', 'company.crew.index', ['company.crew.*'], true),
            self::module('operation', 'incidents', 'company.incidents.index', ['company.incidents.*'], true),
            self::module('channels', 'pos', 'company.pos.index', ['company.pos.*'], true),
            self::module('channels', 'agencies', 'company.agencies.index', ['company.agencies.*'], true),
            self::module('channels', 'parcels', 'company.parcels.index', ['company.parcels.*', 'company.freight.*'], true),
            self::module('channels', 'cash_close', 'company.cash-close.index', ['company.cash-close.*'], true),
            self::module('finance', 'sales', 'company.sales.index', ['company.sales.*', 'company.commissions.*', 'company.settlements.index'], true),
            self::module('finance', 'lots', 'company.agency-lots.index', ['company.agency-lots.*'], true),
            self::module('finance', 'collections', 'company.collections.index', ['company.collections.*'], true),
            self::module('finance', 'refunds', 'company.refunds.index', ['company.refunds.*'], true),
            self::module('finance', 'claims', 'company.claims.index', ['company.claims.*'], false),
            self::module('finance', 'agency_commissions', 'company.agency-commissions.index', ['company.agency-commissions.*'], true),
            self::module('finance', 'receipts', 'company.receipts.index', ['company.receipts.*'], true),
            self::module('finance', 'reports', 'company.reports.index', ['company.reports.*'], true),
            self::module('content', 'page', 'company.profile.edit', ['company.profile.*'], false),
            self::module('content', 'page_sections', 'company.page-sections.index', ['company.page-sections.*'], false),
            self::module('content', 'gallery', 'company.gallery.index', ['company.gallery.*'], false),
            self::module('content', 'policies', 'company.policies.index', ['company.policies.*'], false),
            self::module('content', 'appearance', 'company.appearance.index', ['company.appearance.*'], false),
            self::module('content', 'advertising', 'company.advertising.index', ['company.advertising.*'], false),
            self::module('users', 'team', 'company.staff.index', ['company.staff.*', 'company.personnel.index'], false),
            self::module('users', 'roles', 'company.roles.index', ['company.roles.*'], false),
            self::module('users', 'audit', 'company.audit.index', ['company.audit.*'], false),
            self::module('help', 'messages', 'messages.index', ['messages.*'], false),
            self::module('help', 'support', 'support.index', ['support.*'], false),
            self::module('help', 'whatsapp', 'company.whatsapp.index', ['company.whatsapp.*'], false),
            self::module(null, 'bank', 'company.finance.index', ['company.finance.*'], false, false),
        ];
    }

    /**
     * @param  list<string>  $patterns
     * @return array{group: string|null, key: string, route: string, patterns: list<string>, needs_active: bool, visible: bool}
     */
    private static function module(?string $group, string $key, string $route, array $patterns, bool $needsActive, bool $visible = true): array
    {
        return [
            'group' => $group,
            'key' => $key,
            'route' => $route,
            'patterns' => $patterns,
            'needs_active' => $needsActive,
            'visible' => $visible,
        ];
    }

    private static function isClassified(string $routeName): bool
    {
        foreach (self::modules() as $module) {
            if (self::matches($routeName, $module['patterns'])) {
                return true;
            }
        }

        return false;
    }

    private static function granted(string $profile, string $routeName): bool
    {
        if ($profile === 'seller') {
            return in_array($routeName, self::SELLER_ROUTES, true);
        }
        if ($profile === 'agency') {
            return in_array($routeName, self::AGENCY_ROUTES, true);
        }
        if ($profile === 'finance' && in_array($routeName, self::CASH_WRITE_ROUTES, true)) {
            return false;
        }

        $keys = self::menuKeys()[$profile];
        foreach (self::modules() as $module) {
            if (! in_array($module['key'], $keys, true)) {
                continue;
            }
            if (self::matches($routeName, $module['patterns'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $patterns
     */
    private static function matches(string $routeName, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (Str::is($pattern, $routeName)) {
                return true;
            }
        }

        return false;
    }
}
