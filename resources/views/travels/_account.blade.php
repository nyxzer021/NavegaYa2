@php
    $refunds = $refunds ?? collect();
    $claims = $claims ?? collect();
    $travelSummary = $travelSummary ?? [];
    $tripReviews = $tripReviews ?? collect();
    $certificateIds = $certificateIds ?? [];
    $presentTrip = function ($reservation) use ($refunds, $tripReviews, $certificateIds): array {
        $trip = $reservation->isAir() ? $reservation->airDeparture : $reservation->departure;
        $route = $reservation->isAir() ? $trip?->airRoute : $trip?->transportRoute;
        $origin = $reservation->isAir() ? ($route?->origin_city ?? __('travels.origin')) : ($route?->originPort?->city ?? __('travels.origin'));
        $destination = $reservation->isAir() ? ($route?->destination_city ?? __('travels.destination')) : ($route?->destinationPort?->city ?? __('travels.destination'));
        $place = $reservation->isAir() ? ($route?->origin_city ?? '') : ($route?->originPort?->name ?? '');
        $vehicle = $reservation->isAir() ? ($trip?->aircraft?->name ?? '') : ($trip?->vessel?->name ?? '');
        $when = $trip?->departure_at?->timezone('America/Lima');
        $seatCodes = $reservation->seats
            ->map(fn ($seat) => $reservation->isAir() ? $seat->aircraftSeat?->code : $seat->seat?->code)
            ->filter()
            ->values();
        $ticket = $reservation->seats->first(fn ($seat) => $seat->ticket !== null)?->ticket;
        $refund = $refunds->get($reservation->id);
        $refundOpen = $refund !== null && $refund->status !== 'rejected';
        $canAskRefund = $reservation->status === 'confirmed'
            && $reservation->sales_channel === 'web'
            && (bool) $trip?->departure_at?->isFuture()
            && $trip?->status !== 'cancelled'
            && ! $refundOpen;
        $refunded = $refund !== null && in_array($refund->status, ['approved', 'paid'], true);
        $status = match (true) {
            $refunded => 'refunded',
            in_array($reservation->status, ['cancelled', 'expired'], true) || $trip?->status === 'cancelled' => 'cancelled',
            $trip?->status === 'rescheduled' || str_contains(mb_strtolower((string) $trip?->notes), 'reprogram') => 'changed',
            $trip?->departure_at?->isPast() || in_array($trip?->status, ['departed', 'in_transit', 'completed'], true) => 'done',
            default => 'upcoming',
        };
        $actions = [];
        if ($reservation->status === 'confirmed') {
            $actions[] = ['href' => route('tickets.show', $reservation->code), 'label' => __('travels.view_ticket'), 'variant' => $refunded ? 'secondary' : 'primary'];
            if ($ticket !== null && ! $refunded && $ticket->status !== 'voided') {
                $actions[] = ['href' => route('tickets.pdf', $ticket->code), 'label' => __('travels.download_pdf'), 'variant' => 'secondary'];
            }
        }
        if ($reservation->status === 'confirmed' && ! $refunded && in_array((int) $reservation->id, $certificateIds, true)) {
            $actions[] = ['href' => route('tickets.certificate', $reservation->code), 'label' => __('travels.download_certificate'), 'variant' => 'secondary'];
        }
        if ($reservation->status === 'pending_payment') {
            $actions[] = ['href' => route('payments.show', $reservation->code), 'label' => __('travels.complete_payment'), 'variant' => 'primary'];
        }
        if ($canAskRefund) {
            $actions[] = ['href' => route('tickets.show', $reservation->code).'#reembolso', 'label' => __('travels.ask_refund'), 'variant' => 'secondary'];
        }

        $review = $tripReviews->get($reservation->id);
        $canReview = $status === 'done' && ! $refunded && $trip?->status === 'completed' && $review === null;

        return [
            'review' => $review,
            'canReview' => $canReview,
            'companyId' => $route?->organization_id,
            'origin' => $origin,
            'destination' => $destination,
            'place' => $place,
            'vehicle' => $vehicle,
            'when' => $when,
            'seatCodes' => $seatCodes,
            'ticket' => $ticket,
            'refund' => $refund,
            'status' => $status,
            'actions' => $actions,
            'icon' => $reservation->isAir() ? 'plane' : 'fleet',
            'tone' => $reservation->isAir() ? 'ny-tone-violet' : 'ny-tone-river',
            'mode' => $reservation->isAir() ? __('travels.air') : __('travels.river'),
            'vehicleLabel' => $reservation->isAir() ? __('travels.aircraft') : __('travels.vessel'),
            'placeLabel' => $reservation->isAir() ? __('travels.place') : __('travels.port'),
        ];
    };
    $statusLabel = [
        'upcoming' => __('travels.status_upcoming'),
        'done' => __('travels.status_done'),
        'changed' => __('travels.status_changed'),
        'cancelled' => __('travels.status_cancelled'),
        'refunded' => __('travels.status_refunded'),
    ];
    $statusTone = [
        'upcoming' => 'scheduled',
        'done' => 'done',
        'changed' => 'rescheduled',
        'cancelled' => 'cancelled',
        'refunded' => 'cancelled',
    ];
    $refundTone = [
        'requested' => 'boarding',
        'approved' => 'scheduled',
        'rejected' => 'cancelled',
        'paid' => 'done',
    ];
    $kpis = [
        ['key' => 'upcoming', 'tone' => 'ny-tone-leaf', 'icon' => 'ticket'],
        ['key' => 'completed', 'tone' => 'ny-tone-river', 'icon' => 'check'],
        ['key' => 'changed', 'tone' => 'ny-tone-sun', 'icon' => 'clock'],
        ['key' => 'cancelled', 'tone' => 'ny-tone-coral', 'icon' => 'alert'],
    ];
@endphp
<section class="mt-6 min-w-0 max-w-full space-y-6 overflow-x-clip">
    <header class="flex min-w-0 flex-wrap items-end justify-between gap-3">
        <div class="min-w-0">
            <p class="text-ny-xs font-semibold text-[var(--ny-text-muted)]">{{ __('travels.panel') }}</p>
            <h1 class="text-ny-lg font-extrabold text-[var(--ny-text)]">{{ __('travels.title') }}</h1>
            <p class="mt-1 break-all text-ny-sm text-[var(--ny-text-muted)]">{{ __('travels.greeting', ['name' => auth()->user()->name]) }} · {{ auth()->user()->email }}</p>
        </div>
        <div class="flex max-w-full flex-wrap gap-2">
            <x-ui.button :href="route('profile.edit')">{{ __('travels.edit_profile') }}</x-ui.button>
            <form action="{{ route('logout') }}" method="POST">@csrf<x-ui.button type="submit" variant="primary">{{ __('travels.logout') }}</x-ui.button></form>
        </div>
    </header>
    @unless(auth()->user()->hasVerifiedEmail())
        <x-ui.card class="min-w-0">
            <p class="text-ny-sm text-[var(--ny-text)]">{{ __('travels.verify_email') }}</p>
            <x-ui.button class="mt-3" :href="route('verification.notice')">{{ __('travels.verify_action') }}</x-ui.button>
        </x-ui.card>
    @endunless
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach($kpis as $kpi)
            <x-ui.card class="min-w-0">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="ny-mark {{ $kpi['tone'] }}"><x-ui.icon :name="$kpi['icon']" /></span>
                    <div class="min-w-0">
                        <span class="block text-ny-xs font-semibold text-[var(--ny-text-muted)]">{{ __('travels.'.$kpi['key']) }}</span>
                        <strong class="block text-ny-xl leading-none text-[var(--ny-text)]">{{ $travelSummary[$kpi['key']] ?? 0 }}</strong>
                    </div>
                </div>
            </x-ui.card>
        @endforeach
    </div>
    @if($nextTrip)
        @php($next = $presentTrip($nextTrip))
        <x-ui.card class="min-w-0">
            <div class="flex min-w-0 flex-col gap-4 sm:flex-row sm:items-start">
                <span class="ny-mark {{ $next['tone'] }}"><x-ui.icon :name="$next['icon']" /></span>
                <div class="min-w-0 flex-1">
                    <p class="text-ny-xs font-semibold text-[var(--ny-text-muted)]">{{ __('travels.next') }}</p>
                    <h2 class="mt-1 break-words text-ny-lg font-extrabold text-[var(--ny-text)]">{{ $next['origin'] }} → {{ $next['destination'] }}</h2>
                    <dl class="mt-3 grid min-w-0 gap-3 text-ny-sm sm:grid-cols-2">
                        <div class="min-w-0">
                            <dt class="text-ny-xs font-semibold text-[var(--ny-text-muted)]">{{ __('travels.when') }}</dt>
                            <dd class="break-words font-semibold text-[var(--ny-text)]">{{ $next['when'] ? $next['when']->format('d/m/Y').' · '.$next['when']->format('H:i') : __('travels.pending_date') }}</dd>
                        </div>
                        @if($next['place'] !== '')
                            <div class="min-w-0">
                                <dt class="text-ny-xs font-semibold text-[var(--ny-text-muted)]">{{ $next['placeLabel'] }}</dt>
                                <dd class="break-words font-semibold text-[var(--ny-text)]">{{ $next['place'] }}</dd>
                            </div>
                        @endif
                        @if($next['vehicle'] !== '')
                            <div class="min-w-0">
                                <dt class="text-ny-xs font-semibold text-[var(--ny-text-muted)]">{{ $next['vehicleLabel'] }}</dt>
                                <dd class="break-words font-semibold text-[var(--ny-text)]">{{ $next['vehicle'] }}</dd>
                            </div>
                        @endif
                        @if($next['seatCodes']->isNotEmpty())
                            <div class="min-w-0">
                                <dt class="text-ny-xs font-semibold text-[var(--ny-text-muted)]">{{ $next['seatCodes']->count() > 1 ? __('travels.seats') : __('travels.seat') }}</dt>
                                <dd class="break-words font-semibold text-[var(--ny-text)]">{{ $next['seatCodes']->implode(', ') }}</dd>
                            </div>
                        @endif
                    </dl>
                    <div class="mt-4 flex max-w-full flex-wrap items-center gap-2">
                        @if($next['refund'])
                            <x-ui.badge :tone="$refundTone[$next['refund']->status] ?? 'neutral'">{{ __('refunds.status_'.$next['refund']->status) }}</x-ui.badge>
                        @endif
                        @foreach($next['actions'] as $action)
                            <x-ui.button :href="$action['href']" :variant="$action['variant']">{{ $action['label'] }}</x-ui.button>
                        @endforeach
                    </div>
                </div>
                @if($next['ticket'])
                    <img src="{{ route('tickets.qr', $next['ticket']->code) }}" width="112" height="112" class="h-28 w-28 max-w-full shrink-0 rounded-lg border border-[var(--ny-border)] bg-white p-1" alt="{{ __('travels.qr_alt', ['code' => $next['ticket']->code]) }}">
                @endif
            </div>
        </x-ui.card>
    @endif
    @php($tripView = $tripView ?? 'proximos')
    @php($tripCounts = $tripCounts ?? [])
    @if(session('success'))
        <x-ui.toast :message="session('success')" />
    @endif
    <nav class="flex min-w-0 flex-wrap gap-2" aria-label="{{ __('travels.tabs_label') }}">
        @foreach(['proximos' => [__('travels.tab_upcoming'), 'departures'], 'historial' => [__('travels.tab_history'), 'clock']] as $tabKey => [$tabLabel, $tabIcon])
            <a href="{{ route('travels.index', ['vista' => $tabKey]) }}" @if($tripView === $tabKey) aria-current="page" @endif
               class="inline-flex min-h-11 items-center gap-2 rounded-xl border px-4 text-ny-sm font-extrabold {{ $tripView === $tabKey ? 'border-emerald-700 bg-emerald-700 text-white' : 'border-[var(--ny-border)] bg-white text-[var(--ny-text)] hover:bg-emerald-50' }}">
                <x-ui.icon :name="$tabIcon" class="h-4 w-4" />{{ $tabLabel }}
                <span class="rounded-full px-2 text-ny-xs {{ $tripView === $tabKey ? 'bg-white/20' : 'bg-slate-100' }}">{{ $tripCounts[$tabKey] ?? 0 }}</span>
            </a>
        @endforeach
    </nav>
    <div class="min-w-0 space-y-3">
        @forelse($reservations as $reservation)
            @php($row = $presentTrip($reservation))
            <x-ui.card class="min-w-0">
                <div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-center sm:justify-between" data-trip="{{ $reservation->code }}">
                    <div class="flex min-w-0 items-start gap-3">
                        <span class="ny-mark {{ $row['tone'] }}"><x-ui.icon :name="$row['icon']" /></span>
                        <div class="min-w-0">
                            <div class="flex min-w-0 flex-wrap items-center gap-2">
                                <strong class="break-words text-ny-sm text-[var(--ny-text)]">{{ $row['origin'] }} → {{ $row['destination'] }}</strong>
                                <x-ui.badge :tone="$statusTone[$row['status']]">{{ $statusLabel[$row['status']] }}</x-ui.badge>
                                @if($row['refund'])
                                    <x-ui.badge :tone="$refundTone[$row['refund']->status] ?? 'neutral'">{{ __('refunds.status_'.$row['refund']->status) }}</x-ui.badge>
                                @endif
                            </div>
                            <p class="mt-1 break-words text-ny-xs text-[var(--ny-text-muted)]">{{ $row['when'] ? $row['when']->format('d/m/Y').' · '.$row['when']->format('H:i') : __('travels.pending_date') }} · {{ __('travels.reservation') }} {{ $reservation->code }} · {{ $row['mode'] }}@if($row['seatCodes']->isNotEmpty()) · {{ $row['seatCodes']->count() > 1 ? __('travels.seats') : __('travels.seat') }} {{ $row['seatCodes']->implode(', ') }}@endif</p>
                        </div>
                    </div>
                    @if($row['actions'] !== [])
                        <div class="flex max-w-full flex-wrap gap-2">
                            @foreach($row['actions'] as $action)
                                <x-ui.button :href="$action['href']" :variant="$action['variant']">{{ $action['label'] }}</x-ui.button>
                            @endforeach
                        </div>
                    @endif
                </div>
                @if($row['review'])
                    <p class="mt-3 flex items-center gap-2 text-ny-sm text-[var(--ny-text-muted)]"><x-ui.icon name="star" class="h-4 w-4 text-amber-500" />{{ __('travels.reviewed', ['stars' => $row['review']->rating]) }} @if($row['review']->status === 'pending')· {{ __('travels.review_pending') }}@endif</p>
                @elseif($row['canReview'] && $row['companyId'])
                    <details class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3">
                        <summary class="flex cursor-pointer items-center gap-2 text-ny-sm font-extrabold text-amber-900"><x-ui.icon name="star" class="h-4 w-4" />{{ __('travels.rate_trip') }}</summary>
                        <form method="POST" action="{{ route('companies.review', $row['companyId']) }}" class="mt-3 grid gap-3">
                            @csrf
                            <input type="hidden" name="reservation_id" value="{{ $reservation->id }}">
                            <input type="hidden" name="from" value="mis-viajes">
                            <label class="text-ny-sm font-bold text-[var(--ny-text)]">{{ __('travels.rating') }}
                                <select name="rating" required class="mt-1 min-h-11 w-full rounded-xl border-slate-200">
                                    @foreach([5, 4, 3, 2, 1] as $stars)<option value="{{ $stars }}">{{ $stars }} {{ $stars === 1 ? __('travels.star') : __('travels.stars') }}</option>@endforeach
                                </select>
                            </label>
                            <label class="text-ny-sm font-bold text-[var(--ny-text)]">{{ __('travels.comment') }}
                                <textarea name="comment" required minlength="10" maxlength="1000" rows="3" class="mt-1 w-full rounded-xl border-slate-200" placeholder="{{ __('travels.comment_hint') }}"></textarea>
                            </label>
                            <x-ui.button type="submit" variant="primary">{{ __('travels.send_review') }}</x-ui.button>
                        </form>
                    </details>
                @endif
            </x-ui.card>
        @empty
            <x-ui.card class="min-w-0 text-center">
                <span class="ny-mark ny-tone-leaf mx-auto"><x-ui.icon name="ticket" /></span>
                <strong class="mt-3 block text-ny-md text-[var(--ny-text)]">{{ __('travels.empty_title') }}</strong>
                <p class="mx-auto mt-1 max-w-xl text-ny-sm text-[var(--ny-text-muted)]">{{ __('travels.empty_text') }}</p>
                <x-ui.button class="mt-4" href="{{ route('home') }}#buscar" variant="primary">{{ __('travels.search') }}</x-ui.button>
            </x-ui.card>
        @endforelse
        @if($reservations instanceof \Illuminate\Contracts\Pagination\Paginator && $reservations->hasPages())
            <div class="pt-2">{{ $reservations->links() }}</div>
        @endif
    </div>
    @if($claims->isNotEmpty())
        <section class="min-w-0 space-y-3">
            <h2 class="flex items-center gap-2 text-ny-md font-extrabold text-[var(--ny-text)]"><span class="ny-mark ny-tone-violet"><x-ui.icon name="claims" /></span>{{ __('travels.claims') }}</h2>
            @foreach($claims as $claim)
                <x-ui.card class="min-w-0">
                    <div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex min-w-0 items-start gap-3">
                            <span class="ny-mark ny-tone-sun"><x-ui.icon name="claims" /></span>
                            <div class="min-w-0">
                                <p class="break-all text-ny-sm font-bold text-[var(--ny-text)]">{{ $claim->code }}</p>
                                <p class="text-ny-xs text-[var(--ny-text-muted)]">{{ $claim->filed_on->format('d/m/Y') }}</p>
                            </div>
                        </div>
                        <div class="flex max-w-full flex-wrap items-center gap-2">
                            <x-ui.badge :tone="$claim->answered() ? 'done' : 'boarding'">{{ $claim->answered() ? __('claims.traveler_answered') : __('claims.traveler_within') }}</x-ui.badge>
                            <x-ui.button :href="route('claims.show', $claim->public_token)">{{ __('travels.claim_sheet') }}</x-ui.button>
                        </div>
                    </div>
                </x-ui.card>
            @endforeach
        </section>
    @endif
</section>
