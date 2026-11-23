<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\RouteDeparture;
use App\Models\StaffInvitation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class CompanyAudit
{
    /**
     * @return array<string, list<string>>
     */
    public static function kinds(): array
    {
        return [
            'ventas' => ['sale.counter', 'sale.agency', 'counter_sale_voided'],
            'caja' => ['cash.opened', 'cash.closed'],
            'encomiendas' => ['parcel.delivered'],
            'lotes' => ['lot.created', 'lot.paid', 'lot.released', 'lot_payment.recorded', 'lot_payment.voided'],
            'tarifas' => ['fare.saved', 'fare.removed', 'discount.saved', 'discount.removed'],
            'salidas' => ['departure.cancelled', 'departure.rescheduled'],
            'equipo' => ['staff.invited', 'staff.accepted', 'staff.resent', 'staff.cancelled', 'staff.profile', 'staff.suspended', 'staff.reactivated'],
            'ficha' => ['profile.updated', 'details.updated'],
            'cobros' => ['bank.updated', 'payout.recorded', 'payout.voided', 'web_refund.recorded', 'culqi.confirmed', 'culqi.returned'],
            'reembolsos' => ['refund.requested', 'refund.approved', 'refund.rejected', 'refund.paid', 'refund.card'],
            'comprobantes' => ['receipt.issued'],
        ];
    }

    /**
     * @return Builder<AuditLog>
     */
    public static function query(Organization $organization, Request $request): Builder
    {
        $query = AuditLog::query()
            ->where('organization_id', $organization->id)
            ->with(['user', 'subject'])
            ->latest('created_at')
            ->latest('id');

        $employee = $request->integer('empleado');
        if ($employee > 0) {
            $query->where('user_id', $employee);
        }

        $kind = (string) $request->query('tipo', '');
        $events = self::kinds()[$kind] ?? null;
        if ($events !== null) {
            $query->whereIn('event', $events);
        }

        $from = self::boundary((string) $request->query('desde', ''), false);
        if ($from !== null) {
            $query->where('created_at', '>=', $from);
        }
        $until = self::boundary((string) $request->query('hasta', ''), true);
        if ($until !== null) {
            $query->where('created_at', '<=', $until);
        }

        return $query;
    }

    /**
     * @return array{sentence: string, icon: string, tone: string}
     */
    public static function present(AuditLog $log): array
    {
        $kind = self::kindOf($log->event);

        return [
            'sentence' => self::sentence($log),
            'icon' => match ($kind) {
                'ventas' => 'ticket',
                'caja' => 'cash_close',
                'encomiendas' => 'parcels',
                'lotes' => 'agencies',
                'tarifas' => 'fares',
                'salidas' => 'departures',
                'equipo' => 'team',
                'ficha' => 'page',
                'cobros' => 'online_payments',
                'reembolsos' => 'refunds',
                'comprobantes' => 'receipts',
                default => 'audit',
            },
            'tone' => match ($kind) {
                'ventas' => 'var(--ny-tone-river)',
                'caja' => 'var(--ny-tone-sun)',
                'encomiendas' => 'var(--ny-tone-sun-deep)',
                'lotes' => 'var(--ny-tone-violet)',
                'tarifas' => 'var(--ny-tone-leaf)',
                'salidas' => $log->event === 'departure.cancelled' ? 'var(--ny-tone-coral)' : 'var(--ny-tone-river)',
                'equipo' => 'var(--ny-tone-violet)',
                'ficha' => 'var(--ny-tone-leaf)',
                'cobros' => 'var(--ny-tone-river-deep)',
                'reembolsos' => 'var(--ny-tone-violet)',
                'comprobantes' => 'var(--ny-tone-river)',
                default => 'var(--ny-primary)',
            },
        ];
    }

    public static function routeLabel(RouteDeparture $departure): string
    {
        $departure->loadMissing('transportRoute.originPort', 'transportRoute.destinationPort');
        $origin = $departure->transportRoute?->originPort?->name ?: __('audit.unknown_place');
        $destination = $departure->transportRoute?->destinationPort?->name ?: __('audit.unknown_place');

        return $origin.' → '.$destination;
    }

    public static function money(mixed $amount): string
    {
        return 'S/ '.number_format((float) $amount, 2, '.', '');
    }

    private static function sentence(AuditLog $log): string
    {
        $who = $log->user?->name ?: __('audit.someone');
        $change = $log->changes ?? [];
        $reason = self::text($change, 'reason');
        $reasonText = $reason === '' ? '' : ' ('.__('audit.reason', ['reason' => $reason]).')';

        return match ($log->event) {
            'sale.counter' => __('audit.sale_counter', ['who' => $who, 'code' => self::text($change, 'code'), 'amount' => self::money($change['amount'] ?? 0), 'passenger' => self::text($change, 'passenger')]),
            'sale.agency' => __('audit.sale_agency', ['who' => $who, 'code' => self::text($change, 'code'), 'amount' => self::money($change['amount'] ?? 0), 'agency' => self::text($change, 'agency'), 'passenger' => self::text($change, 'passenger')]),
            'counter_sale_voided' => __('audit.sale_void', ['who' => $who, 'code' => self::text($change, 'code'), 'place' => ($change['channel'] ?? '') === 'agency' ? __('audit.place_agency') : __('audit.place_dock')]).$reasonText,
            'cash.opened' => __('audit.cash_opened', ['who' => $who, 'amount' => self::money($change['float'] ?? 0), 'site' => self::text($change, 'site', __('audit.no_site'))]),
            'cash.closed' => __('audit.cash_closed', ['who' => $who, 'expected' => self::money($change['expected'] ?? 0), 'counted' => self::money($change['counted'] ?? 0), 'difference' => self::money($change['difference'] ?? 0)]),
            'parcel.delivered' => __('audit.parcel_delivered', ['who' => $who, 'code' => self::text($change, 'code'), 'collector' => self::text($change, 'collector')]),
            'lot.created' => __('audit.lot_created', ['who' => $who, 'agency' => self::text($change, 'agency'), 'amount' => self::money($change['price'] ?? 0), 'payment' => ($change['payment'] ?? '') === 'paid' ? __('audit.lot_already_paid') : __('audit.lot_pending')]),
            'lot.paid' => __('audit.lot_paid', ['who' => $who, 'agency' => self::text($change, 'agency')]),
            'lot.released' => __('audit.lot_released', ['who' => $who, 'agency' => self::text($change, 'agency')]).($reason === '' ? '' : $reasonText),
            'lot_payment.recorded' => __('audit.lot_payment', ['who' => $who, 'agency' => self::text($change, 'agency'), 'amount' => self::money($change['amount'] ?? 0), 'operation' => self::text($change, 'operation')]),
            'lot_payment.voided' => __('audit.lot_payment_voided', ['who' => $who, 'agency' => self::text($change, 'agency'), 'amount' => self::money($change['amount'] ?? 0)]).$reasonText,
            'fare.saved' => __('audit.fare_saved', ['who' => $who, 'amount' => self::money($change['amount'] ?? 0), 'passenger' => self::passenger(self::text($change, 'passenger'))]),
            'fare.removed' => __('audit.fare_removed', ['who' => $who, 'amount' => self::money($change['amount'] ?? 0)]),
            'discount.saved' => __('audit.discount_saved', ['who' => $who, 'name' => self::text($change, 'name')]),
            'discount.removed' => __('audit.discount_removed', ['who' => $who, 'name' => self::text($change, 'name')]),
            'departure.cancelled' => __('audit.departure_cancelled', ['who' => $who, 'route' => self::text($change, 'route'), 'when' => self::text($change, 'when')]).$reasonText,
            'departure.rescheduled' => __('audit.departure_rescheduled', ['who' => $who, 'route' => self::text($change, 'route'), 'when' => self::text($change, 'when')]).$reasonText,
            'staff.invited' => __('audit.staff_invited', ['who' => $who, 'name' => self::text($change, 'name'), 'email' => self::text($change, 'email'), 'profile' => self::profile(self::text($change, 'profile'))]),
            'staff.accepted' => __('audit.staff_accepted', ['who' => $who, 'profile' => self::profile(self::text($change, 'profile'))]),
            'staff.resent' => __('audit.staff_resent', ['who' => $who, 'email' => self::text($change, 'email')]),
            'staff.cancelled' => __('audit.staff_cancelled', ['who' => $who, 'email' => self::text($change, 'email')]),
            'staff.profile' => __('audit.staff_profile', ['who' => $who, 'name' => self::subjectName($log), 'from' => self::profile(self::text($change, 'from')), 'to' => self::profile(self::text($change, 'to'))]),
            'staff.suspended' => __('audit.staff_suspended', ['who' => $who, 'name' => self::subjectName($log)]),
            'staff.reactivated' => __('audit.staff_reactivated', ['who' => $who, 'name' => self::subjectName($log)]),
            'profile.updated' => __('audit.profile_updated', ['who' => $who, 'name' => self::text($change, 'name')]),
            'details.updated' => __('audit.details_updated', ['who' => $who, 'name' => self::text($change, 'name')]),
            'bank.updated' => __('audit.bank_updated', ['who' => $who, 'bank' => self::text($change, 'bank')]),
            'payout.recorded' => __('audit.payout_recorded', ['who' => $who, 'amount' => self::money($change['amount'] ?? 0), 'operation' => self::text($change, 'operation')]),
            'payout.voided' => __('audit.payout_voided', ['who' => $who, 'amount' => self::money($change['amount'] ?? 0)]).$reasonText,
            'web_refund.recorded' => __('audit.web_refund', ['who' => $who, 'code' => self::text($change, 'code'), 'amount' => self::money($change['amount'] ?? 0)]).$reasonText,
            'culqi.confirmed' => __('audit.culqi_confirmed', ['who' => $who, 'code' => self::text($change, 'code'), 'amount' => self::money($change['amount'] ?? 0)]),
            'culqi.returned' => __('audit.culqi_returned', ['who' => $who, 'code' => self::text($change, 'code'), 'amount' => self::money($change['amount'] ?? 0)]).$reasonText,
            'refund.requested' => ((float) ($change['amount'] ?? 0)) > 0
                ? __('audit.refund_requested', ['who' => $who, 'code' => self::text($change, 'code'), 'amount' => self::money($change['amount'] ?? 0)])
                : __('audit.refund_requested_open', ['who' => $who, 'code' => self::text($change, 'code')]),
            'refund.approved' => __('audit.refund_approved', ['who' => $who, 'code' => self::text($change, 'code'), 'amount' => self::money($change['amount'] ?? 0)]),
            'refund.rejected' => __('audit.refund_rejected', ['who' => $who, 'code' => self::text($change, 'code')]).$reasonText,
            'refund.paid' => __('audit.refund_paid', ['who' => $who, 'code' => self::text($change, 'code'), 'amount' => self::money($change['amount'] ?? 0)]),
            'refund.card' => __('audit.refund_card', ['who' => $who, 'code' => self::text($change, 'code'), 'amount' => self::money($change['amount'] ?? 0), 'operation' => self::text($change, 'operation')]),
            'receipt.issued' => __('audit.receipt_issued', ['who' => $who, 'number' => self::text($change, 'number'), 'client' => self::text($change, 'client'), 'amount' => self::money($change['amount'] ?? 0)]),
            default => __('audit.generic', ['who' => $who]),
        };
    }

    private static function subjectName(AuditLog $log): string
    {
        $subject = $log->subject;
        if ($subject instanceof User) {
            return $subject->name;
        }
        if ($subject instanceof StaffInvitation) {
            return $subject->name;
        }
        $change = $log->changes ?? [];

        return self::text($change, 'name', self::text($change, 'email', __('audit.someone')));
    }

    private static function kindOf(string $event): ?string
    {
        foreach (self::kinds() as $kind => $events) {
            if (in_array($event, $events, true)) {
                return $kind;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $change
     */
    private static function text(array $change, string $key, string $default = ''): string
    {
        $value = $change[$key] ?? $default;
        if (is_array($value)) {
            return implode(', ', array_map(strval(...), $value));
        }

        return trim((string) $value);
    }

    private static function profile(string $profile): string
    {
        $key = $profile === 'seller' ? 'counter' : $profile;
        $label = __("company_portal.role.{$key}");

        return $label === "company_portal.role.{$key}" ? $profile : $label;
    }

    private static function passenger(string $type): string
    {
        return match ($type) {
            'adult' => __('audit.adult'),
            'child' => __('audit.child'),
            'student' => __('audit.student'),
            'senior' => __('audit.senior'),
            'infant' => __('audit.infant'),
            default => $type,
        };
    }

    private static function boundary(string $value, bool $end): ?Carbon
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        return DepartureSchedule::parse($value.' '.($end ? '23:59:59' : '00:00:00'));
    }
}
