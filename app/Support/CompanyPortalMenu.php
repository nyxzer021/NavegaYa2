<?php

namespace App\Support;

use App\Models\AirDeparture;
use App\Models\BusinessMessage;
use App\Models\Organization;
use App\Models\RouteDeparture;
use App\Models\User;
use Illuminate\Support\Facades\Route;

class CompanyPortalMenu
{
    public function __construct(
        public Organization $organization,
        public User $user,
    ) {}

    public function isAdmin(): bool
    {
        return $this->user->roles()->where('code', 'company_admin')->exists();
    }

    public function isOperational(): bool
    {
        return $this->organization->status === 'active';
    }

    public function roleLabel(): string
    {
        return $this->isAdmin()
            ? __('company_portal.role.admin')
            : __('company_portal.role.counter');
    }

    public function lead(): string
    {
        return $this->isOperational() ? 'operacion' : 'configuracion';
    }

    /**
     * @return array{label: string, tone: string}
     */
    public function salesState(): array
    {
        if ($this->organization->status === 'pending') {
            return ['label' => __('company_portal.sales.pending'), 'tone' => 'amber'];
        }

        if ($this->organization->status !== 'active') {
            return ['label' => __('company_portal.sales.suspended'), 'tone' => 'red'];
        }

        if (! config('culqi.enabled') || blank(config('culqi.public_key')) || blank(config('culqi.secret_key'))) {
            return ['label' => __('company_portal.sales.disconnected'), 'tone' => 'red'];
        }

        if (config('culqi.environment') !== 'live') {
            return ['label' => __('company_portal.sales.testing'), 'tone' => 'amber'];
        }

        return ['label' => __('company_portal.sales.active'), 'tone' => 'green'];
    }

    public function baseLabel(): string
    {
        $base = trim((string) $this->organization->base_city);

        return $base !== '' ? $base : __('company_portal.requires_implementation');
    }

    public function modalityLabel(): string
    {
        return match ($this->organization->modality) {
            'aereo' => __('company_portal.modality.air'),
            'mixto' => __('company_portal.modality.mixed'),
            default => __('company_portal.modality.river'),
        };
    }

    public function profileCompleteness(): int
    {
        $fields = [
            $this->organization->commercial_name,
            $this->organization->public_description,
            $this->organization->service_hours,
            $this->organization->website,
            $this->organization->logo_path,
            $this->organization->cover_image_path,
            $this->organization->whatsapp ?: $this->organization->phone,
            $this->organization->address ?: $this->organization->base_city,
        ];
        $filled = collect($fields)->filter(fn ($value): bool => filled($value))->count();

        return (int) round(($filled / count($fields)) * 100);
    }

    public function todayDepartureCount(): int
    {
        $organizationId = $this->organization->id;
        $river = RouteDeparture::query()
            ->whereHas('transportRoute', fn ($query) => $query->where('organization_id', $organizationId))
            ->whereDate('departure_at', today())
            ->count();
        $air = AirDeparture::query()
            ->whereHas('airRoute', fn ($query) => $query->where('organization_id', $organizationId))
            ->whereDate('departure_at', today())
            ->count();

        return $river + $air;
    }

    public function unreadMessageCount(): int
    {
        return BusinessMessage::query()
            ->join('business_conversations', 'business_conversations.id', '=', 'business_messages.business_conversation_id')
            ->where('business_conversations.organization_id', $this->organization->id)
            ->where('business_messages.sender_id', '!=', $this->user->id)
            ->where(function ($query): void {
                $query->whereNull('business_conversations.business_last_read_at')
                    ->orWhereColumn('business_messages.created_at', '>', 'business_conversations.business_last_read_at');
            })
            ->count();
    }

    /**
     * @return array<int, array{label: string, items: array<int, array<string, mixed>>}>
     */
    public function sections(): array
    {
        $operational = $this->isOperational();
        $admin = $this->isAdmin();
        $sections = [];
        $today = $operational ? $this->todayDepartureCount() : 0;

        if ($admin) {
            $sections[] = [
                'label' => null,
                'items' => [$this->item('home', 'company.dashboard', request()->routeIs('company.dashboard'))],
            ];
        }

        if ($operational) {
            $sections[] = [
                'label' => __('company_portal.section.operation'),
                'items' => [
                    $this->item('departures', 'company.departures.index', request()->routeIs('company.departures.*') && ! request()->routeIs('company.boarding.*'), $today > 0 ? (string) $today : null),
                    $this->item('boarding', 'company.counter.index', request()->routeIs('company.counter.*', 'company.boarding.*')),
                    $this->item('dock', 'company.pos.index', request()->routeIs('company.pos.*')),
                ],
            ];
        }

        if ($admin && $operational) {
            $sections[] = [
                'label' => __('company_portal.section.finance'),
                'items' => [
                    $this->item('sales', 'company.sales.index', request()->routeIs('company.sales.*')),
                    $this->item('collections', 'company.collections.index', request()->routeIs('company.collections.*'), __('company_portal.requires_implementation')),
                    $this->item('refunds', 'company.refunds.index', request()->routeIs('company.refunds.*'), __('company_portal.requires_implementation')),
                    $this->item('service_fee', 'company.commissions.index', request()->routeIs('company.commissions.*', 'company.settlements.*', 'company.finance.*')),
                ],
            ];
        }

        if ($admin) {
            $sections[] = [
                'label' => __('company_portal.section.company'),
                'items' => [
                    $this->item('profile', 'company.profile.edit', request()->routeIs('company.profile.*'), $this->profileCompleteness().'%'),
                    $this->item('fleet', 'company.fleet.index', request()->routeIs('company.fleet.*', 'company.vessels.*', 'company.aircraft.*')),
                    $this->item('team', 'company.staff.index', request()->routeIs('company.staff.*')),
                    $this->item('online_payments', 'company.online-payments.index', request()->routeIs('company.online-payments.*'), null, true),
                ],
            ];
            if ($operational) {
                $sections[] = [
                    'label' => __('company_portal.section.growth'),
                    'items' => [
                        $this->item('advertising', 'company.advertising.index', request()->routeIs('company.advertising.*')),
                    ],
                ];
            }
        }

        $unread = $this->unreadMessageCount();
        $sections[] = [
            'label' => __('company_portal.section.help'),
            'items' => [
                $this->item('messages', 'messages.index', request()->routeIs('messages.*'), $unread > 0 ? (string) $unread : null),
                $this->item('support', 'support.index', request()->routeIs('support.*')),
            ],
        ];

        return $sections;
    }

    public function currentTitle(): ?string
    {
        foreach ($this->sections() as $section) {
            foreach ($section['items'] as $item) {
                if ($item['active']) {
                    return $item['label'];
                }
            }
        }

        return null;
    }

    /**
     * @return array{key: string, label: string, href: string, active: bool, badge: ?string, warning: bool, icon: string}
     */
    private function item(string $key, string $route, bool $active, ?string $badge = null, bool $warning = false): array
    {
        return [
            'key' => $key,
            'label' => __('company_portal.menu.'.$key),
            'href' => Route::has($route) ? route($route) : '#',
            'active' => $active,
            'badge' => $badge,
            'warning' => $warning,
            'icon' => $key,
        ];
    }
}
