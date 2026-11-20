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
        if ($this->user->agency_id) {
            return __('company_portal.role.agency');
        }

        return match (CompanyPermissions::profile($this->user)) {
            'admin' => __('company_portal.role.admin'),
            'agency' => __('company_portal.role.agency'),
            'operator' => __('company_portal.role.operator'),
            'finance' => __('company_portal.role.finance'),
            default => __('company_portal.role.counter'),
        };
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
     * @return array<string, list<string>>
     */
    public function profiles(): array
    {
        return CompanyPermissions::menuKeys();
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
     * @return array<int, array{key: string|null, label: string|null, items: array<int, array<string, mixed>>}>
     */
    public function sections(): array
    {
        $profile = CompanyPermissions::profile($this->user) ?? 'seller';
        $allowed = $this->profiles()[$profile];
        $sections = [];

        foreach ($this->catalog() as $row) {
            if ($row['key'] === 'lots' && $profile !== 'agency') {
                continue;
            }
            if (! in_array('*', $allowed, true) && ! in_array($row['key'], $allowed, true)) {
                continue;
            }
            $group = $row['group'];
            $sections[$group]['key'] = $group;
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
            'label' => in_array($profile, ['seller', 'agency'], true) && $row['key'] === 'sales'
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
        $colors = [
            'home' => '#BA7517',
            'company_details' => '#185FA5',
            'branches' => '#185FA5',
            'online_payments' => '#185FA5',
            'fleet' => '#0F6E56',
            'routes' => '#0F6E56',
            'departures' => '#0F6E56',
            'fares' => '#0F6E56',
            'boarding' => '#0F6E56',
            'crew' => '#0F6E56',
            'incidents' => '#0F6E56',
            'pos' => '#D85A30',
            'agencies' => '#D85A30',
            'parcels' => '#d97706',
            'cash_close' => '#D85A30',
            'sales' => '#3B6D11',
            'lots' => '#6d28d9',
            'collections' => '#3B6D11',
            'refunds' => '#3B6D11',
            'agency_commissions' => '#3B6D11',
            'receipts' => '#3B6D11',
            'reports' => '#3B6D11',
            'page' => '#993556',
            'page_sections' => '#993556',
            'gallery' => '#993556',
            'policies' => '#993556',
            'appearance' => '#993556',
            'advertising' => '#993556',
            'team' => '#534AB7',
            'roles' => '#534AB7',
            'audit' => '#534AB7',
            'messages' => '#5F5E5A',
            'support' => '#5F5E5A',
            'whatsapp' => '#5F5E5A',
        ];
        $rows = [];
        foreach (CompanyPermissions::modules() as $module) {
            if (! $module['visible']) {
                continue;
            }
            $rows[] = $this->row($module['group'], $module['key'], $module['route'], $module['patterns'], $colors[$module['key']], $module['needs_active']);
        }

        return $rows;
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
