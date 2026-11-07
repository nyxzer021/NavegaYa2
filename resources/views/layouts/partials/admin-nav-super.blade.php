@php
    $item = function (string $key, string $href, bool $active, string $color, ?string $icon = null, ?int $badge = null) {
        return compact('key', 'href', 'active', 'color', 'icon', 'badge');
    };
    $groups = [
        ['label' => null, 'items' => [
            $item('home', route('admin.dashboard'), request()->routeIs('admin.dashboard'), '#BA7517', 'dashboard'),
            $item('inbox', route('admin.requests.index'), request()->routeIs('admin.requests.*'), '#BA7517', 'bell', $unreadAdminRequests ? $pendingAdminRequests : null),
        ]],
        ['label' => 'companies', 'items' => [
            $item('companies', route('admin.companies.index'), request()->routeIs('admin.companies.*'), '#185FA5', 'company_details'),
            $item('agencies', route('admin.agencies.index'), request()->routeIs('admin.agencies.*'), '#185FA5', 'agencies'),
            $item('payment_connections', route('admin.payment-connections.index'), request()->routeIs('admin.payment-connections.*'), '#185FA5', 'online_payments'),
        ]],
        ['label' => 'channels', 'items' => [
            $item('sales', route('admin.sales.index'), request()->routeIs('admin.sales.*'), '#D85A30', 'sales'),
            $item('cargo', route('admin.cargo.index'), request()->routeIs('admin.cargo.*'), '#D85A30', 'parcels'),
            $item('reconciliation', route('admin.reconciliation.index'), request()->routeIs('admin.reconciliation.*'), '#D85A30', 'collections'),
            $item('refunds', route('admin.refunds.index'), request()->routeIs('admin.refunds.*'), '#D85A30', 'refunds'),
            $item('service_income', route('admin.commissions.index'), request()->routeIs('admin.commissions.*', 'admin.settlements.*'), '#D85A30', 'service_fee'),
            $item('reports', route('admin.reports.index'), request()->routeIs('admin.reports.*'), '#D85A30', 'reports'),
        ]],
        ['label' => 'operation', 'items' => [
            $item('fleet', route('admin.fleet-compliance.index'), request()->routeIs('admin.fleet-compliance.*'), '#0F6E56', 'fleet', $pendingFleetRequests > 0 ? $pendingFleetRequests : null),
            $item('ports', route('admin.ports.index'), request()->routeIs('admin.ports.*'), '#0F6E56', 'branches'),
            $item('routes', route('admin.master-routes.index'), request()->routeIs('admin.master-routes.*'), '#0F6E56', 'routes'),
            $item('departures', route('admin.itineraries.index'), request()->routeIs('admin.itineraries.*', 'admin.supervision.*'), '#0F6E56', 'departures'),
        ]],
        ['label' => 'marketplace', 'items' => [
            $item('advertisers', route('admin.tourism-partners.index'), request()->routeIs('admin.tourism-partners.*', 'admin.destination-listings.*'), '#534AB7', 'company_details'),
            $item('categories', route('admin.business-categories.index'), request()->routeIs('admin.business-categories.*'), '#534AB7', 'page_sections'),
            $item('experiences', route('admin.tours.index'), request()->routeIs('admin.tours.*'), '#534AB7', 'routes'),
            $item('campaigns', route('admin.advertisements.index'), request()->routeIs('admin.advertisements.*', 'admin.ads.*'), '#534AB7', 'advertising'),
            $item('plans', route('admin.advertising-subscriptions.index'), request()->routeIs('admin.advertising-subscriptions.*'), '#534AB7', 'receipts'),
        ]],
        ['label' => 'content', 'items' => [
            $item('moderation', route('admin.moderation.index'), request()->routeIs('admin.moderation.*'), '#993556', 'roles'),
            $item('base_policies', route('admin.base-policies.index'), request()->routeIs('admin.base-policies.*'), '#993556', 'policies'),
            $item('company_policies', route('admin.company-policies.index'), request()->routeIs('admin.company-policies.*'), '#993556', 'policies'),
            $item('content_moderation', route('admin.content-moderation.index'), request()->routeIs('admin.content-moderation.*'), '#993556', 'roles'),
            $item('navigation', route('admin.navigation.index'), request()->routeIs('admin.navigation.*'), '#993556', 'page'),
        ]],
        ['label' => 'system', 'items' => [
            $item('users', route('admin.users.index'), request()->routeIs('admin.users.*'), '#5F5E5A', 'users'),
            $item('settings', route('admin.settings.index'), request()->routeIs('admin.settings.*'), '#5F5E5A', 'settings'),
            $item('audit', route('admin.audit.index'), request()->routeIs('admin.audit.*'), '#5F5E5A', 'audit'),
        ]],
        ['label' => 'help', 'items' => [
            $item('messages', route('messages.index'), request()->routeIs('messages.*'), '#5F5E5A', 'messages', $unreadBusinessMessages > 0 ? $unreadBusinessMessages : null),
        ]],
    ];
@endphp
<div class="ny-portal">
    @foreach($groups as $group)
        <section>
            @if($group['label'])<p class="ny-portal-label">{{ __('admin_portal.section.'.$group['label']) }}</p>@endif
            @foreach($group['items'] as $link)
                <a href="{{ $link['href'] }}" class="ny-portal-link {{ $link['active'] ? 'is-on' : '' }}">
                    <span class="ny-portal-icon" style="background: {{ $link['color'] }}"><x-ui.icon :name="$link['icon']" /></span>
                    <span>{{ __('admin_portal.menu.'.$link['key']) }}</span>
                    @if($link['badge'])<span class="ny-portal-badge">{{ $link['badge'] }}</span>@endif
                </a>
            @endforeach
        </section>
    @endforeach
</div>
