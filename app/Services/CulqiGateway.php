<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Reservation;
use App\Support\AuditRecorder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class CulqiGateway
{
    public function __construct(private readonly MarketplaceSales $sales) {}

    public function ready(): bool
    {
        return (bool) config('services.culqi.enabled')
            && filled(config('services.culqi.public_key'))
            && filled(config('services.culqi.secret_key'));
    }

    public function testMode(): bool
    {
        return $this->ready() && config('services.culqi.environment') !== 'production';
    }

    public function ticketCents(Reservation $reservation): int
    {
        return $this->cents((float) $this->sales->quote($reservation)['charged_amount']);
    }

    public function cents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    public function signatureValid(string $raw, Request $request): bool
    {
        $secret = (string) config('services.culqi.webhook_secret');
        if ($secret === '') {
            return false;
        }
        $signature = strtolower((string) ($request->header('X-Culqi-Signature') ?? $request->header('Culqi-Signature') ?? ''));
        $expected = hash_hmac('sha256', $raw, $secret);
        if ($this->same($expected, $signature)) {
            return true;
        }

        return $this->same($secret, $this->basicPassword($request));
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function createCharge(array $body): array
    {
        return $this->decode($this->send('post', '/charges', $body, false));
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function createOrder(array $body): array
    {
        return $this->decode($this->send('post', '/orders', $body, false));
    }

    public function probe(): void
    {
        if (! (bool) config('services.culqi.enabled') || ! filled(config('services.culqi.secret_key'))) {
            throw new CulqiException(__('connections.culqi_off'));
        }
        $response = $this->send('get', '/charges?limit=1', null, true);
        if ($response->successful()) {
            return;
        }
        $message = $response->json('user_message');
        throw new CulqiException(is_string($message) && $message !== '' ? mb_substr($message, 0, 180) : __('connections.probe_failed'));
    }

    /**
     * Lee cargos del mes. No conserva tarjeta, correo ni el cuerpo completo.
     *
     * @return list<array{id: string, amount: string, date: string, fee: ?string}>
     */
    public function listCharges(string $from, string $to): array
    {
        if (! (bool) config('services.culqi.enabled') || ! filled(config('services.culqi.secret_key'))) {
            throw new CulqiException(__('connections.culqi_off'));
        }
        $rows = [];
        $before = null;
        for ($page = 0; $page < 5; $page++) {
            $query = array_filter([
                'limit' => 100,
                'creation_date_from' => Carbon::parse($from, 'America/Lima')->startOfDay()->timestamp,
                'creation_date_to' => Carbon::parse($to, 'America/Lima')->endOfDay()->timestamp,
                'before' => $before,
            ], fn (mixed $value): bool => $value !== null && $value !== '');
            $response = $this->send('get', '/charges?'.http_build_query($query), null, true);
            if (! $response->successful()) {
                $message = $response->json('user_message');
                throw new CulqiException(is_string($message) && $message !== '' ? mb_substr($message, 0, 180) : __('reconciliation.fetch_failed'));
            }
            $json = $response->json();
            $data = is_array($json) && is_array($json['data'] ?? null) ? $json['data'] : [];
            foreach ($data as $charge) {
                $row = $this->chargeRow(is_array($charge) ? $charge : [], $from, $to);
                if ($row !== null) {
                    $rows[] = $row;
                }
            }
            $cursor = is_array($json) ? ($json['paging']['cursors']['before'] ?? null) : null;
            if (! is_string($cursor) || preg_match('/^[A-Za-z0-9_\-=]{1,80}$/', $cursor) !== 1 || $data === []) {
                break;
            }
            $before = $cursor;
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    public function retrieveCharge(string $id): array
    {
        $this->guardId($id, 'chr_');

        return $this->decode($this->send('get', '/charges/'.$id, null, true));
    }

    /**
     * @return array<string, mixed>
     */
    public function retrieveOrder(string $id): array
    {
        $this->guardId($id, 'ord_');

        return $this->decode($this->send('get', '/orders/'.$id, null, true));
    }

    public function refund(string $chargeId, int $cents, string $reason): bool
    {
        try {
            $this->issueRefund($chargeId, $cents, $reason);

            return true;
        } catch (CulqiException) {
            return false;
        }
    }

    public function issueRefund(string $chargeId, int $cents, string $reason): string
    {
        $this->guardId($chargeId, 'chr_');
        if ($cents < 1) {
            throw new CulqiException(__('refunds.card_failed'));
        }
        if (! in_array($reason, ['duplicado', 'fraudulento', 'solicitud_comprador'], true)) {
            $reason = 'solicitud_comprador';
        }
        $refund = $this->decode($this->send('post', '/refunds', [
            'amount' => $cents,
            'charge_id' => $chargeId,
            'reason' => $reason,
        ], false));
        $id = $refund['id'] ?? null;
        if (! is_string($id) || preg_match('/^ref_[A-Za-z0-9_]+$/', $id) !== 1) {
            throw new CulqiException(__('refunds.card_failed'));
        }

        return $id;
    }

    /**
     * Guarda solo lo necesario para conciliar. Nunca el número, el CVV ni el vencimiento.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function safePayload(array $payload): array
    {
        $source = is_array($payload['source'] ?? null) ? $payload['source'] : [];
        $kept = [
            'id' => $payload['id'] ?? null,
            'object' => $payload['object'] ?? null,
            'amount' => $payload['amount'] ?? null,
            'currency_code' => $payload['currency_code'] ?? null,
            'state' => $payload['state'] ?? ($payload['status'] ?? null),
            'brand' => $source['brand'] ?? null,
            'last_four' => $this->lastFour($source),
            'source_id' => isset($source['id']) && is_string($source['id']) ? $source['id'] : null,
            'payment_code' => $payload['payment_code'] ?? null,
        ];

        return array_filter($kept, fn (mixed $value): bool => $value !== null && $value !== '');
    }

    public function recordConfirmation(Reservation $reservation, Payment $payment): void
    {
        AuditRecorder::record(request(), 'culqi.confirmed', $payment, [
            'code' => $reservation->code,
            'amount' => (string) $payment->amount,
            'charge' => (string) $payment->provider_reference,
        ], $this->organizationId($reservation));
    }

    public function releaseCharge(Reservation $reservation, string $chargeId, int $cents, string $reason): void
    {
        $existing = Payment::query()->where('provider_reference', $chargeId)->first();
        if ($existing && in_array($existing->status, ['refunded', 'review_required', 'confirmed', 'succeeded'], true)) {
            return;
        }
        $refunded = $this->refund($chargeId, $cents, $reason === 'duplicado' ? 'duplicado' : 'solicitud_comprador');
        $payment = Payment::query()->updateOrCreate(
            ['provider_reference' => $chargeId],
            [
                'reservation_id' => $reservation->id,
                'purpose' => 'ticket',
                'method' => 'card',
                'provider' => 'culqi',
                'amount' => number_format($cents / 100, 2, '.', ''),
                'currency_code' => 'PEN',
                'status' => $refunded ? 'refunded' : 'review_required',
                'requires_review' => ! $refunded,
                'review_reason' => $refunded ? null : __('payments.refund_failed'),
                'provider_payload' => ['id' => $chargeId, 'refunded' => $refunded],
            ],
        );
        AuditRecorder::record(request(), 'culqi.returned', $payment, [
            'code' => $reservation->code,
            'amount' => number_format($cents / 100, 2, '.', ''),
            'charge' => $chargeId,
            'reason' => $reason,
        ], $this->organizationId($reservation));
    }

    public function userMessage(Response $response): string
    {
        $message = $response->json('user_message');
        if (is_string($message) && $message !== '') {
            return mb_substr($message, 0, 180);
        }

        return __('payments.culqi_rejected');
    }

    /**
     * @param  array<string, mixed>|null  $body
     */
    private function send(string $method, string $path, ?array $body, bool $retry): Response
    {
        $secret = (string) config('services.culqi.secret_key');
        if ($secret === '') {
            throw new CulqiException(__('payments.culqi_off'));
        }
        $pending = Http::withToken($secret)->acceptJson()->asJson()->timeout(20)->connectTimeout(5);
        if ($retry) {
            $pending = $pending->retry(3, 250, function (mixed $exception, mixed ...$ignored): bool {
                return $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && (bool) $exception->response?->serverError());
            }, throw: false);
        }
        $url = rtrim((string) config('services.culqi.api_url'), '/').$path;
        try {
            return $method === 'get' ? $pending->get($url) : $pending->post($url, $body ?? []);
        } catch (ConnectionException) {
            throw new CulqiException(__('payments.culqi_unreachable'));
        } catch (RequestException $exception) {
            return $exception->response;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        $json = $response->json();
        if (! $response->successful() || ! is_array($json)) {
            throw new CulqiException($this->userMessage($response));
        }

        return $json;
    }

    /**
     * @param  array<string, mixed>  $charge
     * @return array{id: string, amount: string, date: string, fee: ?string}|null
     */
    private function chargeRow(array $charge, string $from, string $to): ?array
    {
        $id = $charge['id'] ?? null;
        $cents = $charge['amount'] ?? null;
        $created = $charge['creation_date'] ?? null;
        if (! is_string($id) || preg_match('/^chr_[A-Za-z0-9_]+$/', $id) !== 1) {
            return null;
        }
        if (! is_int($cents) && ! (is_string($cents) && preg_match('/^\d+$/', $cents) === 1)) {
            return null;
        }
        if (! is_int($created) && ! (is_string($created) && preg_match('/^\d+$/', $created) === 1)) {
            return null;
        }
        $date = Carbon::createFromTimestamp((int) $created, 'America/Lima')->toDateString();
        if ($date < $from || $date > $to) {
            return null;
        }
        $fee = null;
        $totalFee = $charge['total_fee'] ?? null;
        if (is_int($totalFee) || (is_string($totalFee) && preg_match('/^\d+$/', $totalFee) === 1)) {
            $fee = $this->decimalFromCents((int) $totalFee);
        }

        return [
            'id' => $id,
            'amount' => $this->decimalFromCents((int) $cents),
            'date' => $date,
            'fee' => $fee,
        ];
    }

    private function decimalFromCents(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    private function guardId(string $id, string $prefix): void
    {
        if (preg_match('/^'.preg_quote($prefix, '/').'[A-Za-z0-9_]+$/', $id) !== 1) {
            throw new CulqiException(__('payments.culqi_rejected'));
        }
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private function lastFour(array $source): ?string
    {
        $last = $source['last_four'] ?? null;
        if (is_string($last) && preg_match('/^\d{4}$/', $last) === 1) {
            return $last;
        }

        return null;
    }

    private function basicPassword(Request $request): string
    {
        $fromSapi = (string) $request->getPassword();
        if ($fromSapi !== '') {
            return $fromSapi;
        }
        $header = (string) $request->header('Authorization');
        if (! str_starts_with($header, 'Basic ')) {
            return '';
        }
        $decoded = base64_decode(substr($header, 6), true);
        if (! is_string($decoded) || ! str_contains($decoded, ':')) {
            return '';
        }

        return substr($decoded, strpos($decoded, ':') + 1);
    }

    private function same(string $known, string $given): bool
    {
        if ($known === '' || strlen($known) !== strlen($given)) {
            return false;
        }

        return hash_equals($known, $given);
    }

    private function organizationId(Reservation $reservation): ?int
    {
        $reservation->loadMissing('departure.transportRoute', 'airDeparture.airRoute');
        $id = $reservation->departure?->transportRoute?->organization_id
            ?? $reservation->airDeparture?->airRoute?->organization_id;

        return $id === null ? null : (int) $id;
    }
}
