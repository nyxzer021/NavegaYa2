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
        return ['label' => __('company_portal.sales.configure'), 'tone' => 'amber'];
    }

    public function hasBasePort(): bool
    {
        return $this->organization->base_port_id !== null;
    }

    public function baseLabel(): string
    {
        return CompanyBase::label($this->organization) ?? __('company_portal.base_undefined');
    }

    /**
     * Perfiles de la etapa 6. Hoy solo se usan admin y seller.
     *
     * @return array<string, list<string>>
     */
    public function profiles(): array
    {
        return [
            'admin' => ['*'],
            'seller' => ['pos', 'parcels', 'freight', 'sales', 'cash_close'],
            'operator' => ['departures', 'boarding', 'fleet', 'crew', 'incidents'],
            'finance' => ['sales', 'collections', 'refunds', 'agency_commissions', 'receipts', 'reports'],
        ];
    }

    public function modalityLabel(): string
    {
        $this->organization->loadMissing('modalities');
        $approved = $this->organization->modalities->where('status', 'approved');
        if ($approved->isNotEmpty()) {
            return $approved->map(fn ($row) => __('company_portal.modality.'.$row->code))->implode(' · ');
        }

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
            ->tap(fn ($query) => DepartureSchedule::onThisLimaDay($query))
            ->count();
        $air = AirDeparture::query()
            ->whereHas('airRoute', fn ($query) => $query->where('organization_id', $organizationId))
            ->tap(fn ($query) => DepartureSchedule::onThisLimaDay($query))
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
        $profile = $this->isAdmin() ? 'admin' : 'seller';
        $allowed = $this->profiles()[$profile];
        $sections = [];

        foreach ($this->catalog() as $row) {
            if (! in_array('*', $allowed, true) && ! in_array($row['key'], $allowed, true)) {
                continue;
            }
            $group = $row['group'];
            $sections[$group]['label'] = $group === null ? null : __('company_portal.section.'.$group);
            $sections[$group]['items'][] = $this->item($row, $profile);
        }

        return array_values($sections);
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
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function item(array $row, string $profile): array
    {
        $reason = $this->lockReason($row['key'], $profile, (bool) $row['needs_active']);
        $active = $reason === null && request()->routeIs(...$row['patterns']);
        $badge = null;
        if ($reason === null && $row['key'] === 'departures') {
            $today = $this->todayDepartureCount();
            $badge = $today > 0 ? (string) $today : null;
        }
        if ($reason === null && $row['key'] === 'page') {
            $percent = $this->profileCompleteness();
            $badge = $percent > 0 ? $percent.'%' : null;
        }
        if ($reason === null && $row['key'] === 'messages') {
            $unread = $this->unreadMessageCount();
            $badge = $unread > 0 ? (string) $unread : null;
        }

        return [
            'key' => $row['key'],
            'label' => $profile === 'seller' && $row['key'] === 'sales'
                ? __('company_portal.menu.my_sales')
                : __('company_portal.menu.'.$row['key']),
            'href' => $reason === null && Route::has($row['route']) ? route($row['route']) : null,
            'active' => $active,
            'badge' => $badge,
            'icon' => $row['key'],
            'color' => $row['color'],
            'group' => $row['group'] === null ? null : __('company_portal.section.'.$row['group']),
            'locked' => $reason !== null,
            'reason' => $reason,
        ];
    }

    private function lockReason(string $key, string $profile, bool $needsActive): ?string
    {
        if (($needsActive || $key === 'advertising') && ! $this->isOperational()) {
            return __('company_portal.lock.pending');
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function catalog(): array
    {
        $home = '#BA7517';
        $company = '#185FA5';
        $channels = '#D85A30';
        $finance = '#3B6D11';
        $operation = '#0F6E56';
        $users = '#534AB7';
        $content = '#993556';
        $help = '#5F5E5A';

        return [
            $this->row(null, 'home', 'company.dashboard', ['company.dashboard'], $home, false),
            $this->row('company', 'company_details', 'company.details.index', ['company.details.*'], $company, false),
            $this->row('company', 'branches', 'company.branches.index', ['company.branches.*'], $company, false),
            $this->row('company', 'online_payments', 'company.online-payments.index', ['company.online-payments.*'], $company, false),
            $this->row('channels', 'pos', 'company.pos.index', ['company.pos.*'], $channels, true),
            $this->row('channels', 'agencies', 'company.agencies.index', ['company.agencies.*'], $channels, true),
            $this->row('channels', 'parcels', 'company.parcels.index', ['company.parcels.*'], $channels, true),
            $this->row('channels', 'freight', 'company.freight.index', ['company.freight.*'], $channels, true),
            $this->row('channels', 'cash_close', 'company.cash-close.index', ['company.cash-close.*'], $channels, true),
            $this->row('finance', 'sales', 'company.sales.index', ['company.sales.*', 'company.commissions.*'], $finance, true),
            $this->row('finance', 'collections', 'company.collections.index', ['company.collections.*'], $finance, true),
            $this->row('finance', 'refunds', 'company.refunds.index', ['company.refunds.*'], $finance, true),
            $this->row('finance', 'agency_commissions', 'company.agency-commissions.index', ['company.agency-commissions.*'], $finance, true),
            $this->row('finance', 'receipts', 'company.receipts.index', ['company.receipts.*'], $finance, true),
            $this->row('finance', 'reports', 'company.reports.index', ['company.reports.*'], $finance, true),
            $this->row('operation', 'fleet', 'company.fleet.index', ['company.fleet.*', 'company.vessels.*', 'company.aircraft.*'], $operation, true),
            $this->row('operation', 'routes', 'company.routes.index', ['company.routes.*'], $operation, true),
            $this->row('operation', 'departures', 'company.departures.index', ['company.departures.index', 'company.departures.create', 'company.departures.edit', 'company.departures.store', 'company.departures.update', 'company.departures.status', 'company.departures.contingency'], $operation, true),
            $this->row('operation', 'fares', 'company.fares.index', ['company.fares.*'], $operation, true),
            $this->row('operation', 'boarding', 'company.counter.index', ['company.counter.*', 'company.boarding.*', 'company.departures.manifest'], $operation, true),
            $this->row('operation', 'crew', 'company.crew.index', ['company.crew.*'], $operation, true),
            $this->row('operation', 'incidents', 'company.incidents.index', ['company.incidents.*'], $operation, true),
            $this->row('users', 'team', 'company.staff.index', ['company.staff.*'], $users, false),
            $this->row('users', 'roles', 'company.roles.index', ['company.roles.*'], $users, false),
            $this->row('users', 'audit', 'company.audit.index', ['company.audit.*'], $users, false),
            $this->row('content', 'page', 'company.profile.edit', ['company.profile.*'], $content, false),
            $this->row('content', 'page_sections', 'company.page-sections.index', ['company.page-sections.*'], $content, false),
            $this->row('content', 'gallery', 'company.gallery.index', ['company.gallery.*'], $content, false),
            $this->row('content', 'policies', 'company.policies.index', ['company.policies.*'], $content, false),
            $this->row('content', 'appearance', 'company.appearance.index', ['company.appearance.*'], $content, false),
            $this->row('content', 'advertising', 'company.advertising.index', ['company.advertising.*'], $content, false),
            $this->row('help', 'messages', 'messages.index', ['messages.*'], $help, false),
            $this->row('help', 'support', 'support.index', ['support.*'], $help, false),
            $this->row('help', 'whatsapp', 'company.whatsapp.index', ['company.whatsapp.*'], $help, false),
        ];
    }

    /**
     * @param  list<string>  $patterns
     * @return array<string, mixed>
     */
    private function row(?string $group, string $key, string $route, array $patterns, string $color, bool $needsActive): array
    {
        return [
            'group' => $group,
            'key' => $key,
            'route' => $route,
            'patterns' => $patterns,
            'color' => $color,
            'needs_active' => $needsActive,
        ];
    }
}
