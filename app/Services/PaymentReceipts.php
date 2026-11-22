<?php

namespace App\Services;

use App\Contracts\ElectronicInvoicing;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentReceipt;
use App\Models\ReceiptSequence;
use App\Support\AuditRecorder;
use App\Support\DepartureSchedule;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PaymentReceipts
{
    public function __construct(private readonly ElectronicInvoicing $invoicing) {}

    public function organizationId(Payment $payment): ?int
    {
        $payment->loadMissing(
            'reservation.departure.transportRoute',
            'reservation.airDeparture.airRoute',
            'cargoShipment',
        );

        return $payment->reservation?->departure?->transportRoute?->organization_id
            ?? $payment->reservation?->airDeparture?->airRoute?->organization_id
            ?? $payment->cargoShipment?->organization_id;
    }

    /**
     * El número CP-AAAA-000001 es de la empresa. El contador se bloquea para que
     * dos ventas a la vez no reciban el mismo correlativo.
     */
    public function issue(Payment $payment): ?PaymentReceipt
    {
        $organizationId = $this->organizationId($payment);
        if ($organizationId === null || ! in_array($payment->purpose, ['ticket', 'cargo'], true)) {
            return null;
        }

        return DB::transaction(function () use ($payment, $organizationId): PaymentReceipt {
            $existing = PaymentReceipt::query()->where('payment_id', $payment->id)->lockForUpdate()->first();
            if ($existing !== null) {
                return $existing;
            }

            $year = (int) now()->timezone(DepartureSchedule::ZONE)->format('Y');
            $now = now();
            ReceiptSequence::query()->insertOrIgnore([
                'organization_id' => $organizationId,
                'series_year' => $year,
                'last_number' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $counter = ReceiptSequence::query()
                ->where('organization_id', $organizationId)
                ->where('series_year', $year)
                ->lockForUpdate()
                ->firstOrFail();
            $sequence = (int) $counter->last_number + 1;
            $counter->update(['last_number' => $sequence]);
            $receipt = PaymentReceipt::query()->create([
                'organization_id' => $organizationId,
                'payment_id' => $payment->id,
                'series_year' => $year,
                'sequence' => $sequence,
                'number' => sprintf('CP-%d-%06d', $year, $sequence),
                'issued_at' => $now,
            ]);
            AuditRecorder::record(request(), 'receipt.issued', $receipt, [
                'number' => $receipt->number,
                'amount' => (string) $payment->amount,
                'client' => $this->client($payment),
            ], $organizationId);

            return $receipt;
        });
    }

    /**
     * @param  array{desde?: string, hasta?: string, tipo?: string, cliente?: string}  $filters
     * @return array{rows: list<array<string, mixed>>, kpis: array{receipts: int, tickets: int, ticket_cents: int, parcels: int, parcel_cents: int, invoices: int}}
     */
    public function catalog(Organization $organization, array $filters): array
    {
        $query = $this->scoped($organization->id, $filters);
        $rows = (clone $query)
            ->with([
                'receipt',
                'reservation.seats.ticket',
                'reservation.departure.transportRoute.originPort',
                'reservation.departure.transportRoute.destinationPort',
                'cargoShipment',
            ])
            ->latest('paid_at')
            ->latest('id')
            ->get()
            ->map(fn (Payment $payment): array => $this->row($payment))
            ->all();

        return [
            'rows' => $rows,
            'kpis' => [
                'receipts' => (clone $query)->whereHas('receipt')->count(),
                'tickets' => (clone $query)->where('purpose', 'ticket')->where('status', 'confirmed')->count(),
                'ticket_cents' => $this->sumCents((clone $query)->where('purpose', 'ticket')->where('status', 'confirmed')),
                'parcels' => (clone $query)->where('purpose', 'cargo')->where('status', 'confirmed')->count(),
                'parcel_cents' => $this->sumCents((clone $query)->where('purpose', 'cargo')->where('status', 'confirmed')),
                'invoices' => (clone $query)->whereHas('reservation', fn (Builder $reservation) => $reservation->where('needs_invoice', true))->count(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function certificate(PaymentReceipt $receipt): array
    {
        $receipt->loadMissing('organization', 'payment.reservation.seats.ticket', 'payment.cargoShipment');
        $row = $this->row($receipt->payment);
        $electronic = $this->invoicing->issue([
            'number' => $receipt->number,
            'amount' => $this->soles($row['amount_cents']),
            'buyer_name' => $row['client'],
            'buyer_document' => $row['document'],
        ]);

        return [
            'number' => $receipt->number,
            'issued' => DepartureSchedule::show($receipt->issued_at, 'Y-m-d H:i'),
            'company' => $receipt->organization?->commercial_name ?: $receipt->organization?->legal_name,
            'company_ruc' => $receipt->organization?->ruc,
            'client' => $row['client'],
            'document' => $row['document'],
            'address' => $row['address'],
            'amount' => $this->soles($row['amount_cents']),
            'kind' => $row['kind_label'],
            'reference' => $row['reference'],
            'disclaimer' => __('receipts.not_sunat'),
            'electronic' => $electronic['message'],
            'electronic_issued' => $electronic['issued'],
        ];
    }

    public function soles(int $cents): string
    {
        return 'S/ '.number_format($cents / 100, 2, '.', '');
    }

    /**
     * @param  array{desde?: string, hasta?: string, tipo?: string, cliente?: string}  $filters
     * @return Builder<Payment>
     */
    private function scoped(int $organizationId, array $filters): Builder
    {
        $query = Payment::query()
            ->whereIn('purpose', ['ticket', 'cargo'])
            ->where(function (Builder $owned) use ($organizationId): void {
                $owned->whereHas('reservation.departure.transportRoute', fn (Builder $route) => $route->where('organization_id', $organizationId))
                    ->orWhereHas('reservation.airDeparture.airRoute', fn (Builder $route) => $route->where('organization_id', $organizationId))
                    ->orWhereHas('cargoShipment', fn (Builder $cargo) => $cargo->where('organization_id', $organizationId));
            });

        $kind = (string) ($filters['tipo'] ?? '');
        if ($kind === 'boleto') {
            $query->where('purpose', 'ticket');
        } elseif ($kind === 'encomienda') {
            $query->where('purpose', 'cargo');
        }

        $from = $this->boundary((string) ($filters['desde'] ?? ''), false);
        if ($from !== null) {
            $query->where('paid_at', '>=', $from);
        }
        $until = $this->boundary((string) ($filters['hasta'] ?? ''), true);
        if ($until !== null) {
            $query->where('paid_at', '<=', $until);
        }

        $client = trim((string) ($filters['cliente'] ?? ''));
        if ($client !== '') {
            $like = '%'.addcslashes($client, '%_\\').'%';
            $query->where(function (Builder $match) use ($like): void {
                $match->whereHas('reservation', function (Builder $reservation) use ($like): void {
                    $reservation->where('contact_name', 'like', $like)
                        ->orWhere('buyer_legal_name', 'like', $like)
                        ->orWhere('buyer_document_number', 'like', $like)
                        ->orWhere('contact_document', 'like', $like);
                })->orWhereHas('cargoShipment', function (Builder $cargo) use ($like): void {
                    $cargo->where('sender_name', 'like', $like)
                        ->orWhere('sender_document', 'like', $like)
                        ->orWhere('recipient_name', 'like', $like)
                        ->orWhere('code', 'like', $like);
                });
            });
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Payment $payment): array
    {
        $payment->loadMissing('receipt', 'reservation.seats.ticket', 'cargoShipment');
        $reservation = $payment->reservation;
        $cargo = $payment->cargoShipment;
        $invoice = (bool) $reservation?->needs_invoice;
        $client = $cargo !== null
            ? (string) $cargo->sender_name
            : (string) ($invoice ? $reservation?->buyer_legal_name : $reservation?->contact_name);
        $document = $cargo !== null
            ? (string) $cargo->sender_document
            : trim((string) $reservation?->buyer_document_type.' '.(string) $reservation?->buyer_document_number);
        $rawChannel = $cargo !== null ? 'parcel' : (string) ($reservation?->sales_channel ?: 'counter');
        $channel = in_array($rawChannel, ['web', 'agency', 'counter', 'parcel'], true) ? $rawChannel : 'counter';
        $tickets = $reservation?->seats
            ->map(fn ($seat): ?array => $seat->ticket === null ? null : ['id' => $seat->ticket->id, 'code' => $seat->ticket->code])
            ->filter()
            ->values()
            ->all() ?? [];

        return [
            'payment' => $payment,
            'receipt' => $payment->receipt,
            'when' => $payment->paid_at,
            'client' => $client !== '' ? $client : '—',
            'document' => $document !== '' ? $document : '—',
            'address' => $reservation?->buyer_address,
            'kind' => $payment->purpose === 'cargo' ? 'parcel' : 'ticket',
            'kind_label' => $payment->purpose === 'cargo' ? __('receipts.kind_parcel') : __('receipts.kind_ticket'),
            'channel' => $channel,
            'channel_label' => __('receipts.channel_'.$channel),
            'tone' => match ($channel) {
                'web' => 'var(--ny-tone-river)',
                'agency' => 'var(--ny-tone-violet)',
                'parcel' => 'var(--ny-tone-sun)',
                default => 'var(--ny-tone-leaf)',
            },
            'icon' => $payment->purpose === 'cargo' ? 'parcels' : 'ticket',
            'status' => (string) $payment->status,
            'status_label' => in_array($payment->status, ['confirmed', 'voided', 'refunded'], true)
                ? __('receipts.status_'.$payment->status)
                : __('receipts.status_pending'),
            'status_tone' => match ($payment->status) {
                'voided' => 'var(--ny-tone-coral)',
                'refunded' => 'var(--ny-tone-violet)',
                default => 'var(--ny-tone-leaf)',
            },
            'amount_cents' => (int) round(((float) $payment->amount) * 100),
            'invoice' => $invoice,
            'tickets' => $tickets,
            'shipment_id' => $cargo?->id,
            'reference' => $cargo?->code ?: $reservation?->code,
        ];
    }

    private function client(Payment $payment): string
    {
        return $this->row($payment)['client'];
    }

    private function sumCents(Builder $query): int
    {
        return (int) $query->pluck('amount')->sum(fn (mixed $amount): int => (int) round(((float) $amount) * 100));
    }

    private function boundary(string $value, bool $end): ?Carbon
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }
        $day = Carbon::parse($value, DepartureSchedule::ZONE);

        return $end ? $day->copy()->endOfDay()->utc() : $day->copy()->startOfDay()->utc();
    }
}
