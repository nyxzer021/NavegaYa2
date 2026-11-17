<?php

namespace App\Services;

use App\Models\CargoShipment;
use App\Models\CashSession;
use App\Models\OrganizationSite;
use App\Models\Payment;
use App\Models\User;
use App\Support\AdminScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CashDesk
{
    public function open(User $user, int $organizationId, ?int $siteId, float $float): CashSession
    {
        if ($this->cents($float) < 0) {
            throw ValidationException::withMessages(['opening_float' => 'El fondo inicial no puede ser negativo.']);
        }
        $siteId = $this->siteId($organizationId, $user, $siteId);

        return DB::transaction(function () use ($user, $organizationId, $siteId, $float): CashSession {
            if ($this->lockedOpen($user->id, $organizationId, $siteId) !== null) {
                throw ValidationException::withMessages(['opening_float' => 'Ya tienes un turno abierto en esta sede.']);
            }

            return CashSession::query()->create([
                'organization_id' => $organizationId,
                'organization_site_id' => $siteId,
                'user_id' => $user->id,
                'opened_at' => now(),
                'opening_float' => round($float, 2),
            ]);
        });
    }

    public function requireOpen(User $user, int $organizationId, ?int $siteId): CashSession
    {
        $match = $this->findOpen($user, $organizationId, $siteId);
        if ($match !== null) {
            return $match;
        }

        $open = CashSession::query()
            ->where('user_id', $user->id)
            ->where('organization_id', $organizationId)
            ->whereNull('closed_at')
            ->get();
        if ($open->count() === 1 && $open->first()->organization_site_id === null) {
            return $open->first();
        }

        throw ValidationException::withMessages([
            'cash_session' => 'Abre la caja con el fondo inicial antes de cobrar.',
        ]);
    }

    public function findOpen(User $user, int $organizationId, ?int $siteId): ?CashSession
    {
        return CashSession::query()
            ->where('user_id', $user->id)
            ->where('organization_id', $organizationId)
            ->whereNull('closed_at')
            ->when(
                $siteId === null,
                fn ($query) => $query->whereNull('organization_site_id'),
                fn ($query) => $query->where('organization_site_id', $siteId),
            )
            ->first();
    }

    public function close(User $user, CashSession $session, float $counted, ?string $note): CashSession
    {
        if ($this->cents($counted) < 0) {
            throw ValidationException::withMessages(['counted_cash' => 'El efectivo contado no puede ser negativo.']);
        }

        return DB::transaction(function () use ($user, $session, $counted, $note): CashSession {
            $locked = CashSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            $this->assertCanTouch($user, $locked);
            abort_unless($locked->closed_at === null, 422);
            $expected = $this->expectedCash($locked);
            $difference = round($counted - $expected, 2);
            if ($this->cents($difference) !== 0 && blank($note)) {
                throw ValidationException::withMessages(['closing_note' => 'Si hay diferencia, la nota es obligatoria.']);
            }
            $locked->update([
                'closed_at' => now(),
                'counted_cash' => round($counted, 2),
                'expected_cash' => $expected,
                'difference' => $difference,
                'closing_note' => blank($note) ? null : trim((string) $note),
                'closed_by_user_id' => $user->id,
            ]);

            return $locked->refresh();
        });
    }

    public function expectedCash(CashSession $session): float
    {
        $cents = $this->cents((float) $session->opening_float);
        $payments = Payment::query()
            ->where('cash_session_id', $session->id)
            ->where('status', 'confirmed')
            ->whereNull('voided_at')
            ->where('method', 'cash')
            ->get(['cash_tendered', 'cash_change']);
        foreach ($payments as $payment) {
            $cents += $this->cents((float) ($payment->cash_tendered ?? 0)) - $this->cents((float) ($payment->cash_change ?? 0));
        }

        return round($cents / 100, 2);
    }

    /**
     * @return array{purposes: array<string, float>, methods: array<string, float>, sellers: array<string, float>, operations: list<array{method: string, number: string, amount: float, purpose: string}>, commission: float, expected: float}
     */
    public function summary(CashSession $session): array
    {
        $payments = Payment::query()
            ->where('cash_session_id', $session->id)
            ->where('status', 'confirmed')
            ->whereNull('voided_at')
            ->with(['collector', 'reservation.soldBy'])
            ->get();
        $purposes = [];
        $methods = [];
        $sellers = [];
        $operations = [];
        $commission = 0;
        foreach ($payments as $payment) {
            $purpose = $payment->purpose === 'cargo' ? 'cargo' : 'ticket';
            $this->add($purposes, $purpose, (float) $payment->amount);
            $this->add($methods, (string) $payment->method, (float) $payment->amount);
            $seller = $payment->collector?->name ?: $payment->reservation?->soldBy?->name ?: __('cash.unknown_seller');
            $this->add($sellers, $seller, (float) $payment->amount);
            $commission += $this->cents((float) $payment->commission_amount);
            if (in_array($payment->method, ['yape', 'plin', 'transfer', 'card'], true) && filled($payment->operation_number)) {
                $operations[] = [
                    'method' => (string) $payment->method,
                    'number' => (string) $payment->operation_number,
                    'amount' => (float) $payment->amount,
                    'purpose' => $purpose,
                ];
            }
        }

        return [
            'purposes' => $purposes,
            'methods' => $methods,
            'sellers' => $sellers,
            'operations' => $operations,
            'commission' => round($commission / 100, 2),
            'expected' => $session->closed_at ? round((float) $session->expected_cash, 2) : $this->expectedCash($session),
        ];
    }

    /**
     * @return Collection<int, CargoShipment>
     */
    public function receivable(int $organizationId): Collection
    {
        return CargoShipment::query()
            ->where('organization_id', $organizationId)
            ->where('payment_status', 'pending')
            ->where('payment_method', 'recipient')
            ->where('status', '!=', 'delivered')
            ->with(['departure.transportRoute.originPort', 'departure.transportRoute.destinationPort', 'site'])
            ->orderBy('received_at')
            ->get();
    }

    public function moveVoid(User $user, Payment $payment, string $reason): bool
    {
        if (! $payment->cash_session_id) {
            return false;
        }
        $closed = CashSession::query()->whereKey($payment->cash_session_id)->whereNotNull('closed_at')->first();
        if ($closed === null) {
            return false;
        }
        $open = $this->findOpen($user, (int) $closed->organization_id, $payment->organization_site_id ?: $closed->organization_site_id);
        if ($open === null) {
            $open = CashSession::query()
                ->where('user_id', $user->id)
                ->where('organization_id', $closed->organization_id)
                ->whereNull('closed_at')
                ->first();
        }
        if ($open === null) {
            throw ValidationException::withMessages(['reason' => 'Abre la caja para registrar la devolución.']);
        }
        $net = $payment->method === 'cash'
            ? round((float) ($payment->cash_tendered ?? $payment->amount) - (float) ($payment->cash_change ?? 0), 2)
            : null;
        Payment::query()->create([
            'reservation_id' => $payment->reservation_id,
            'cargo_shipment_id' => $payment->cargo_shipment_id,
            'purpose' => $payment->purpose ?: 'ticket',
            'method' => $payment->method,
            'provider' => 'pos',
            'provider_reference' => 'DEV-'.$payment->id.'-'.Str::upper(Str::random(4)),
            'amount' => round(-1 * (float) $payment->amount, 2),
            'cash_tendered' => $net === null ? null : round(-1 * $net, 2),
            'cash_change' => $net === null ? null : 0,
            'adjustment_note' => $reason,
            'currency_code' => 'PEN',
            'commission_rate' => 0,
            'commission_amount' => round(-1 * (float) $payment->commission_amount, 2),
            'operator_net' => round(-1 * (float) $payment->operator_net, 2),
            'status' => 'confirmed',
            'paid_at' => now(),
            'collected_by_user_id' => $user->id,
            'organization_site_id' => $open->organization_site_id,
            'cash_session_id' => $open->id,
        ]);

        return true;
    }

    public function assertCanTouch(User $user, CashSession $session): void
    {
        abort_unless((int) $session->organization_id === (int) AdminScope::organizationId($user), 403);
        if ((int) $session->user_id !== (int) $user->id && ! $user->isCompanyAdmin()) {
            abort(403);
        }
    }

    public function cents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    private function lockedOpen(int $userId, int $organizationId, ?int $siteId): ?CashSession
    {
        return CashSession::query()
            ->where('user_id', $userId)
            ->where('organization_id', $organizationId)
            ->whereNull('closed_at')
            ->when(
                $siteId === null,
                fn ($query) => $query->whereNull('organization_site_id'),
                fn ($query) => $query->where('organization_site_id', $siteId),
            )
            ->lockForUpdate()
            ->first();
    }

    private function siteId(int $organizationId, User $user, mixed $siteId): ?int
    {
        if ($user->organization_site_id) {
            $siteId = $user->organization_site_id;
        }
        $exists = OrganizationSite::query()->where('organization_id', $organizationId)->where('is_active', true)->exists();
        if (! $exists) {
            return null;
        }
        $site = OrganizationSite::query()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->whereKey($siteId)
            ->first();
        if ($site === null) {
            throw ValidationException::withMessages(['site_id' => 'Elige la sede de la caja.']);
        }

        return $site->id;
    }

    /**
     * @param  array<string, float>  $bag
     */
    private function add(array &$bag, string $key, float $amount): void
    {
        $bag[$key] = round(($bag[$key] ?? 0) + $amount, 2);
    }
}
