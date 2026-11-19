<?php

namespace App\Services;

use App\Models\CargoGuideCounter;
use App\Models\CargoShipment;
use App\Models\CargoShipmentEvent;
use App\Models\Organization;
use App\Models\OrganizationSite;
use App\Models\Payment;
use App\Models\RouteDeparture;
use App\Models\User;
use App\Support\DepartureSchedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CargoDesk
{
    public function __construct(private readonly CashDesk $cash) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function register(User $seller, Organization $organization, array $data): CargoShipment
    {
        return DB::transaction(function () use ($seller, $organization, $data): CargoShipment {
            $departure = $this->departure($organization, $data['route_departure_id'] ?? null);
            $weight = round((float) $data['declared_weight_kg'], 2);
            if ($departure !== null) {
                $this->assertCapacity($departure, $weight);
            }
            $amount = round((float) $data['amount'], 2);
            $method = (string) $data['method'];
            if ($method !== 'recipient') {
                $this->assertPayment($data, $amount);
            }
            $siteId = $this->siteId($organization->id, $data['site_id'] ?? null);

            $shipment = $this->createShipment($organization, $departure, $seller, $data, $weight, $amount, $method, $siteId);
            $this->event($shipment, 'received', $seller->id, null);
            if ($method !== 'recipient') {
                $session = $this->cash->requireOpen($seller, $organization->id, $siteId);
                $this->collect($shipment, $seller, $siteId, $data, $amount, $session->id);
            }

            return $shipment->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function assign(User $seller, CargoShipment $shipment, array $data): CargoShipment
    {
        return DB::transaction(function () use ($seller, $shipment, $data): CargoShipment {
            $locked = $this->lockShipment($shipment);
            abort_unless($locked->status === 'received', 422);
            $organization = $locked->organization;
            $departure = $this->departure($organization, $data['route_departure_id'] ?? null);
            if ($departure === null) {
                throw ValidationException::withMessages(['route_departure_id' => 'Elige una salida.']);
            }
            $this->assertCapacity($departure, (float) $locked->declared_weight_kg, $locked->id);
            $locked->update([
                'route_departure_id' => $departure->id,
                'needs_reassignment' => false,
            ]);
            $this->event($locked, 'assigned', $seller->id, null);

            return $locked->refresh();
        });
    }

    public function board(User $seller, CargoShipment $shipment): CargoShipment
    {
        return DB::transaction(function () use ($seller, $shipment): CargoShipment {
            $locked = $this->lockShipment($shipment);
            abort_unless($locked->status === 'received' && $locked->route_departure_id, 422);
            $locked->update([
                'status' => 'boarded',
                'boarded_at' => now(),
                'boarded_by_user_id' => $seller->id,
            ]);
            $this->event($locked, 'boarded', $seller->id, null);

            return $locked->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function deliver(User $seller, CargoShipment $shipment, array $data): CargoShipment
    {
        return DB::transaction(function () use ($seller, $shipment, $data): CargoShipment {
            $locked = $this->lockShipment($shipment);
            abort_unless($locked->status === 'boarded', 422);
            if (blank($data['collector_document'] ?? null)) {
                throw ValidationException::withMessages([
                    'collector_document' => 'Indica el documento de quien recoge.',
                ]);
            }
            $siteId = $locked->organization_site_id;
            if ($locked->payment_status !== 'paid') {
                if (! in_array($data['method'] ?? '', ['cash', 'yape', 'card'], true)) {
                    throw ValidationException::withMessages(['method' => 'Elige cómo paga el destinatario.']);
                }
                $this->assertPayment($data, (float) $locked->amount);
                $siteId = $this->siteId($locked->organization_id, $data['site_id'] ?? null);
                $session = $this->cash->requireOpen($seller, (int) $locked->organization_id, $siteId);
                $this->collect($locked, $seller, $siteId, $data, (float) $locked->amount, $session->id);
            }
            $locked->update([
                'status' => 'delivered',
                'delivered_at' => now(),
                'delivered_by_user_id' => $seller->id,
                'collector_name' => $data['collector_name'] ?? null,
                'collector_document' => $data['collector_document'],
                'payment_status' => 'paid',
                'payment_method' => $locked->payment_status === 'paid' ? $locked->payment_method : $data['method'],
                'collected_by_user_id' => $locked->collected_by_user_id ?: $seller->id,
                'organization_site_id' => $siteId,
            ]);
            $this->event($locked, 'delivered', $seller->id, (string) $data['collector_document']);

            return $locked->refresh();
        });
    }

    public function releaseDeparture(RouteDeparture $departure): void
    {
        DB::transaction(function () use ($departure): void {
            $rows = CargoShipment::query()
                ->where('route_departure_id', $departure->id)
                ->where('status', '!=', 'delivered')
                ->lockForUpdate()
                ->get();
            foreach ($rows as $shipment) {
                $shipment->update([
                    'route_departure_id' => null,
                    'status' => 'received',
                    'needs_reassignment' => true,
                ]);
                $this->event($shipment, 'unassigned', $departure->status_changed_by_user_id, 'La salida fue cancelada.');
            }
        });
    }

    /**
     * @return array{used: float, limit: float|null, percent: float|null, tone: string|null}
     */
    public function capacity(RouteDeparture $departure): array
    {
        $used = round((float) CargoShipment::query()->where('route_departure_id', $departure->id)->sum('declared_weight_kg'), 2);
        $tons = $departure->vessel?->cargo_capacity_tons;
        if ($tons === null) {
            return ['used' => $used, 'limit' => null, 'percent' => null, 'tone' => null];
        }
        $limit = round((float) $tons * 1000, 2);
        $percent = $limit <= 0 ? 100.0 : min(100, round(($used / $limit) * 100, 1));
        $tone = $percent >= 90 ? 'coral' : ($percent >= 70 ? 'sun' : 'leaf');

        return ['used' => $used, 'limit' => $limit, 'percent' => $percent, 'tone' => $tone];
    }

    private function lockShipment(CargoShipment $shipment): CargoShipment
    {
        $locked = CargoShipment::query()->whereKey($shipment->id)->lockForUpdate()->firstOrFail();
        $locked->load('organization');

        return $locked;
    }

    private function departure(Organization $organization, mixed $departureId): ?RouteDeparture
    {
        if (blank($departureId)) {
            return null;
        }
        $departure = RouteDeparture::query()->with(['transportRoute', 'vessel'])->whereKey($departureId)->lockForUpdate()->first();
        if ($departure === null || (int) $departure->transportRoute?->organization_id !== (int) $organization->id) {
            abort(403);
        }
        if (in_array($departure->status, ['cancelled', 'completed'], true)) {
            throw ValidationException::withMessages(['route_departure_id' => 'Esa salida ya no recibe carga.']);
        }

        return $departure;
    }

    private function assertCapacity(RouteDeparture $departure, float $weightKg, ?int $ignoreId = null): void
    {
        $tons = $departure->vessel?->cargo_capacity_tons;
        if ($tons === null) {
            return;
        }
        $limit = (int) round(((float) $tons) * 1000 * 100);
        $used = CargoShipment::query()
            ->where('route_departure_id', $departure->id)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->lockForUpdate()
            ->get()
            ->sum(fn (CargoShipment $row): int => (int) round(((float) $row->declared_weight_kg) * 100));
        $adding = (int) round($weightKg * 100);
        if ($used + $adding > $limit) {
            throw ValidationException::withMessages([
                'route_departure_id' => 'El peso supera la capacidad de carga de la embarcación.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertPayment(array $data, float $total): void
    {
        if (($data['method'] ?? null) === 'cash') {
            $tendered = round((float) ($data['cash_tendered'] ?? 0), 2);
            if ($this->cents($tendered) < $this->cents($total)) {
                throw ValidationException::withMessages([
                    'cash_tendered' => 'El monto recibido no alcanza para el total.',
                ]);
            }

            return;
        }
        if (blank($data['operation_number'] ?? null)) {
            throw ValidationException::withMessages([
                'operation_number' => 'El número de operación es obligatorio.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function collect(CargoShipment $shipment, User $seller, ?int $siteId, array $data, float $amount, int $sessionId): void
    {
        $method = (string) $data['method'];
        $tendered = $method === 'cash' ? round((float) $data['cash_tendered'], 2) : null;
        $change = $tendered === null ? null : round($tendered - $amount, 2);
        Payment::query()->create([
            'cargo_shipment_id' => $shipment->id,
            'purpose' => 'cargo',
            'method' => $method,
            'provider' => 'pos',
            'provider_reference' => 'CARGO-'.$shipment->code,
            'amount' => $amount,
            'cash_tendered' => $tendered,
            'cash_change' => $change,
            'operation_number' => $data['operation_number'] ?? null,
            'currency_code' => 'PEN',
            'commission_rate' => 0,
            'commission_amount' => 0,
            'operator_net' => $amount,
            'status' => 'confirmed',
            'paid_at' => now(),
            'collected_by_user_id' => $seller->id,
            'organization_site_id' => $siteId,
            'cash_session_id' => $sessionId,
        ]);
    }

    private function siteId(int $organizationId, mixed $siteId): ?int
    {
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
            throw ValidationException::withMessages(['site_id' => 'Elige la sede donde cobras.']);
        }

        return $site->id;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createShipment(Organization $organization, ?RouteDeparture $departure, User $seller, array $data, float $weight, float $amount, string $method, ?int $siteId): CargoShipment
    {
        $payload = [
            'organization_id' => $organization->id,
            'route_departure_id' => $departure?->id,
            'sender_name' => $data['sender_name'],
            'sender_document' => $data['sender_document'],
            'sender_phone' => $data['sender_phone'],
            'recipient_name' => $data['recipient_name'],
            'recipient_document' => $data['recipient_document'],
            'recipient_phone' => $data['recipient_phone'],
            'description' => $data['description'],
            'package_count' => (int) $data['package_count'],
            'declared_weight_kg' => $weight,
            'verified_weight_kg' => $weight,
            'length_cm' => (int) $data['length_cm'],
            'width_cm' => (int) $data['width_cm'],
            'height_cm' => (int) $data['height_cm'],
            'fragile' => (bool) ($data['fragile'] ?? false),
            'amount' => $amount,
            'payment_method' => $method,
            'payment_status' => $method === 'recipient' ? 'pending' : 'paid',
            'status' => 'received',
            'received_at' => now(),
            'received_by_user_id' => $seller->id,
            'collected_by_user_id' => $method === 'recipient' ? null : $seller->id,
            'organization_site_id' => $method === 'recipient' ? null : $siteId,
            'received_at_site_id' => $siteId,
        ];

        $payload['code'] = $this->nextCode($organization);

        return CargoShipment::query()->create($payload);
    }

    private function nextCode(Organization $organization): string
    {
        $sigla = strtoupper((string) $organization->short_code);
        if (! preg_match('/^[A-Z]{3,6}$/', $sigla)) {
            throw ValidationException::withMessages([
                'code' => 'Define la sigla de la empresa, de 3 a 6 letras, en Datos de la empresa.',
            ]);
        }
        $day = now(DepartureSchedule::ZONE)->toDateString();
        $now = now();
        CargoGuideCounter::query()->insertOrIgnore([
            'organization_id' => $organization->id,
            'lima_on' => $day,
            'last_number' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $counter = CargoGuideCounter::query()
            ->where('organization_id', $organization->id)
            ->where('lima_on', $day)
            ->lockForUpdate()
            ->firstOrFail();
        $number = (int) $counter->last_number + 1;
        $counter->update(['last_number' => $number]);

        return sprintf('ENC-%s-%s-%04d', $sigla, str_replace('-', '', $day), $number);
    }

    private function event(CargoShipment $shipment, string $kind, ?int $userId, ?string $note): void
    {
        CargoShipmentEvent::query()->create([
            'cargo_shipment_id' => $shipment->id,
            'kind' => $kind,
            'user_id' => $userId,
            'happened_at' => now(),
            'note' => $note,
        ]);
    }

    private function cents(float $amount): int
    {
        return (int) round($amount * 100);
    }
}
