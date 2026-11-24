<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Reservation;
use App\Models\TicketRefund;
use App\Support\AuditRecorder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class MercadoPagoGateway
{
    public function __construct(private readonly MarketplaceSales $sales) {}

    public function ready(): bool
    {
        return $this->enabled() && filled(config('services.mercadopago.access_token'));
    }

    public function testMode(): bool
    {
        return $this->ready() && config('services.mercadopago.environment') !== 'production';
    }

    public function ticketCents(Reservation $reservation): int
    {
        return $this->cents((float) $this->sales->quote($reservation)['charged_amount']);
    }

    public function signatureValid(Request $request): bool
    {
        $secret = (string) config('services.mercadopago.webhook_secret');
        $id = $this->notificationId($request);
        $requestId = (string) $request->header('x-request-id');
        $header = (string) $request->header('x-signature');
        if ($secret === '' || $id === null || preg_match('/^[A-Za-z0-9-]{1,80}$/', $requestId) !== 1) {
            return false;
        }
        if (preg_match('/ts=(\d{1,20}),v1=([a-f0-9]{64})/', $header, $match) !== 1) {
            return false;
        }
        $manifest = 'id:'.$id.';request-id:'.$requestId.';ts:'.$match[1].';';

        return hash_equals(hash_hmac('sha256', $manifest, $secret), strtolower($match[2]));
    }

    public function notificationId(Request $request): ?string
    {
        $query = (string) $request->server('QUERY_STRING');
        if (preg_match('/(?:^|&)data\.id=([^&]+)/', $query, $match) !== 1 && preg_match('/(?:^|&)data_id=([^&]+)/', $query, $match) !== 1) {
            return null;
        }
        $id = rawurldecode($match[1]);
        if (preg_match('/[A-Za-z]/', $id) === 1) {
            $id = strtolower($id);
        }
        if (preg_match('/^\d{1,20}$/', $id) !== 1) {
            return null;
        }

        return $id;
    }

    public function createCheckoutUrl(Reservation $reservation): string
    {
        $cents = $this->ticketCents($reservation);
        $preference = $this->decode($this->send('post', '/checkout/preferences', [
            'items' => [[
                'title' => 'Pasajes NavegaYA '.$reservation->code,
                'quantity' => 1,
                'unit_price' => $this->soles($cents),
                'currency_id' => 'PEN',
            ]],
            'external_reference' => $reservation->code,
            'notification_url' => route('webhooks.mercadopago'),
            'back_urls' => [
                'success' => route('payments.mercadopago.return', $reservation->code),
                'pending' => route('payments.mercadopago.return', $reservation->code),
                'failure' => route('payments.mercadopago.return', $reservation->code),
            ],
            'auto_return' => 'approved',
            'metadata' => ['reservation_code' => $reservation->code],
            'statement_descriptor' => 'NAVEGAYA',
        ], false));
        $echoed = $preference['items'][0]['unit_price'] ?? null;
        $reference = $preference['external_reference'] ?? null;
        if ((is_array($preference['items'] ?? null) && $this->centsFromMixed($echoed) !== $cents) || (is_string($reference) && $reference !== $reservation->code)) {
            throw new MercadoPagoException(__('payments.amount_rejected'));
        }

        return $this->checkoutUrl($preference);
    }

    /**
     * @return array<string, mixed>
     */
    public function retrievePayment(string $id): array
    {
        $this->guardId($id);

        return $this->decode($this->send('get', '/v1/payments/'.$id, null, true));
    }

    public function issueRefund(string $reference, int $cents): string
    {
        $id = $this->numericId($reference);
        if ($cents < 1) {
            throw new MercadoPagoException(__('refunds.mp_failed'));
        }
        $paid = TicketRefund::query()
            ->where('status', 'paid')
            ->whereHas('payment', fn ($query) => $query->where('provider_reference', $reference))
            ->count();
        $refund = $this->decode($this->send('post', '/v1/payments/'.$id.'/refunds', [
            'amount' => $this->soles($cents),
        ], false, 'ny-'.$reference.'-'.$cents.'-'.$paid));
        $refundId = $refund['id'] ?? null;
        if ((! is_int($refundId) && ! (is_string($refundId) && preg_match('/^\d{1,20}$/', $refundId) === 1)) || ($refund['status'] ?? null) !== 'approved') {
            throw new MercadoPagoException(__('refunds.mp_failed'));
        }

        return 'mpr_'.$refundId;
    }

    public function release(Reservation $reservation, string $reference, int $cents): void
    {
        $existing = Payment::query()->where('provider_reference', $reference)->first();
        if ($existing && in_array($existing->status, ['refunded', 'review_required', 'confirmed', 'succeeded'], true)) {
            return;
        }
        $refunded = false;
        try {
            $this->issueRefund($reference, $cents);
            $refunded = true;
        } catch (MercadoPagoException) {
            $refunded = false;
        }
        $payment = Payment::query()->updateOrCreate(
            ['provider_reference' => $reference],
            [
                'reservation_id' => $reservation->id,
                'purpose' => 'ticket',
                'method' => 'card',
                'provider' => 'mercadopago',
                'amount' => $this->decimal($cents),
                'currency_code' => 'PEN',
                'status' => $refunded ? 'refunded' : 'review_required',
                'requires_review' => ! $refunded,
                'review_reason' => $refunded ? null : __('payments.refund_failed'),
                'provider_payload' => ['id' => $reference, 'refunded' => $refunded],
            ],
        );
        AuditRecorder::record(request(), 'mp.returned', $payment, [
            'code' => $reservation->code,
            'amount' => $this->decimal($cents),
            'charge' => $reference,
        ], $this->organizationId($reservation));
    }

    public function recordConfirmation(Reservation $reservation, Payment $payment): void
    {
        AuditRecorder::record(request(), 'mp.confirmed', $payment, [
            'code' => $reservation->code,
            'amount' => (string) $payment->amount,
            'charge' => (string) $payment->provider_reference,
        ], $this->organizationId($reservation));
    }

    public function probe(): void
    {
        if (! $this->enabled() || ! filled(config('services.mercadopago.access_token'))) {
            throw new MercadoPagoException(__('connections.mp_off'));
        }
        $response = $this->send('get', '/users/me', null, true);
        if ($response->successful()) {
            return;
        }
        throw new MercadoPagoException($this->safeMessage($response, __('connections.probe_failed')));
    }

    /**
     * Cargos y reembolsos del mes. No conserva tarjeta, correo ni el cuerpo completo.
     *
     * @return list<array{kind: string, id: string, amount: string, date: string, fee: ?string, net: ?string}>
     */
    public function listMovements(string $from, string $to): array
    {
        if (! $this->enabled() || ! filled(config('services.mercadopago.access_token'))) {
            throw new MercadoPagoException(__('connections.mp_off'));
        }
        $rows = [];
        $offset = 0;
        for ($page = 0; $page < 5; $page++) {
            $query = http_build_query([
                'sort' => 'date_created',
                'criteria' => 'desc',
                'range' => 'date_created',
                'begin_date' => Carbon::parse($from, 'America/Lima')->startOfDay()->toIso8601String(),
                'end_date' => Carbon::parse($to, 'America/Lima')->endOfDay()->toIso8601String(),
                'limit' => 50,
                'offset' => $offset,
            ]);
            $response = $this->send('get', '/v1/payments/search?'.$query, null, true);
            if (! $response->successful()) {
                throw new MercadoPagoException($this->safeMessage($response, __('reconciliation.fetch_failed')));
            }
            $json = $response->json();
            $results = is_array($json) && is_array($json['results'] ?? null) ? $json['results'] : [];
            foreach ($results as $payment) {
                foreach ($this->movementRows(is_array($payment) ? $payment : [], $from, $to) as $row) {
                    $rows[] = $row;
                }
            }
            $total = is_array($json) ? (int) ($json['paging']['total'] ?? 0) : 0;
            $offset += 50;
            if ($results === [] || $offset >= $total) {
                break;
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $payment
     * @return array<string, mixed>
     */
    public function safePayload(array $payment): array
    {
        $card = is_array($payment['card'] ?? null) ? $payment['card'] : [];
        $last = $card['last_four_digits'] ?? null;
        $kept = [
            'id' => isset($payment['id']) ? 'mp_'.$payment['id'] : null,
            'status' => $payment['status'] ?? null,
            'amount' => $payment['transaction_amount'] ?? null,
            'currency_id' => $payment['currency_id'] ?? null,
            'brand' => is_string($payment['payment_method_id'] ?? null) ? $payment['payment_method_id'] : null,
            'last_four' => is_string($last) && preg_match('/^\d{4}$/', $last) === 1 ? $last : null,
        ];

        return array_filter($kept, fn (mixed $value): bool => $value !== null && $value !== '');
    }

    public function reference(string $id): string
    {
        $this->guardId($id);

        return 'mp_'.$id;
    }

    public function centsFromMixed(mixed $amount): ?int
    {
        if (is_string($amount)) {
            $amount = str_replace(',', '.', trim($amount));
        }
        if (! is_int($amount) && ! is_float($amount) && ! (is_string($amount) && is_numeric($amount))) {
            return null;
        }
        $normal = number_format((float) $amount, 2, '.', '');
        if (preg_match('/^(\d{1,7})\.(\d{2})$/', $normal, $match) !== 1) {
            return null;
        }

        return ((int) $match[1]) * 100 + (int) $match[2];
    }

    public function decimal(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>|null  $body
     */
    private function send(string $method, string $path, ?array $body, bool $retry, ?string $idempotency = null): Response
    {
        $token = (string) config('services.mercadopago.access_token');
        if ($token === '') {
            throw new MercadoPagoException(__('payments.mp_off'));
        }
        $pending = Http::withToken($token)->acceptJson()->asJson()->timeout(20)->connectTimeout(5)->withoutRedirecting();
        if ($idempotency !== null) {
            $pending = $pending->withHeaders(['X-Idempotency-Key' => $idempotency]);
        }
        if ($retry) {
            $pending = $pending->retry(3, 250, function (mixed $exception, mixed ...$ignored): bool {
                return $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && (bool) $exception->response?->serverError());
            }, throw: false);
        }
        $url = rtrim((string) config('services.mercadopago.api_url'), '/').$path;
        try {
            $response = $method === 'get' ? $pending->get($url) : $pending->post($url, $body ?? []);
        } catch (ConnectionException) {
            throw new MercadoPagoException(__('payments.mp_unreachable'));
        } catch (RequestException $exception) {
            $response = $exception->response;
        }
        if (! $response->successful()) {
            throw new MercadoPagoException($this->safeMessage($response, $method === 'post' && str_contains($path, '/refunds') ? __('refunds.mp_failed') : __('payments.mp_rejected')));
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        $json = $response->json();
        if (! is_array($json)) {
            throw new MercadoPagoException(__('payments.mp_rejected'));
        }

        return $json;
    }

    /**
     * @param  array<string, mixed>  $preference
     */
    private function checkoutUrl(array $preference): string
    {
        $key = config('services.mercadopago.environment') === 'production' ? 'init_point' : 'sandbox_init_point';
        $url = $preference[$key] ?? null;
        $host = is_string($url) ? parse_url($url, PHP_URL_HOST) : null;
        if (! is_string($host) || preg_match('/(^|\.)mercadopago\.(com|com\.pe)$/', strtolower($host)) !== 1) {
            throw new MercadoPagoException(__('payments.mp_rejected'));
        }

        return $url;
    }

    /**
     * @param  array<string, mixed>  $payment
     * @return list<array{kind: string, id: string, amount: string, date: string, fee: ?string, net: ?string}>
     */
    private function movementRows(array $payment, string $from, string $to): array
    {
        $id = $payment['id'] ?? null;
        $status = $payment['status'] ?? null;
        $created = $payment['date_created'] ?? null;
        if ((! is_int($id) && ! (is_string($id) && preg_match('/^\d{1,20}$/', $id) === 1)) || ! in_array($status, ['approved', 'refunded'], true)) {
            return [];
        }
        if (! is_string($created) || preg_match('/^\d{4}-\d{2}-\d{2}T/', $created) !== 1) {
            return [];
        }
        $date = Carbon::parse($created)->timezone('America/Lima')->toDateString();
        if ($date < $from || $date > $to || ($payment['currency_id'] ?? null) !== 'PEN') {
            return [];
        }
        $cents = $this->centsFromMixed($payment['transaction_amount'] ?? null);
        if ($cents === null) {
            return [];
        }
        $fee = $this->feeCents($payment);
        $rows = [[
            'kind' => 'charge',
            'id' => 'mp_'.$id,
            'amount' => $this->decimal($cents),
            'date' => $date,
            'fee' => $fee === null ? null : $this->decimal($fee),
            'net' => $fee === null ? null : $this->decimal($cents - $fee),
        ]];
        $refunds = is_array($payment['refunds'] ?? null) ? $payment['refunds'] : [];
        foreach ($refunds as $refund) {
            if (! is_array($refund) || ($refund['status'] ?? null) !== 'approved') {
                continue;
            }
            $refundId = $refund['id'] ?? null;
            $refundCents = $this->centsFromMixed($refund['amount'] ?? null);
            if ((! is_int($refundId) && ! (is_string($refundId) && preg_match('/^\d{1,20}$/', $refundId) === 1)) || $refundCents === null) {
                continue;
            }
            $rows[] = [
                'kind' => 'refund',
                'id' => 'mpr_'.$refundId,
                'amount' => $this->decimal($refundCents),
                'date' => $date,
                'fee' => null,
                'net' => null,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $payment
     */
    private function feeCents(array $payment): ?int
    {
        $details = $payment['fee_details'] ?? null;
        if (! is_array($details)) {
            return null;
        }
        $cents = 0;
        $found = false;
        foreach ($details as $detail) {
            if (! is_array($detail) || ($detail['type'] ?? null) !== 'mercadopago_fee') {
                continue;
            }
            $part = $this->centsFromMixed($detail['amount'] ?? null);
            if ($part !== null) {
                $cents += $part;
                $found = true;
            }
        }

        return $found ? $cents : null;
    }

    private function safeMessage(Response $response, string $fallback): string
    {
        $message = $response->json('message');
        if (! is_string($message) || $message === '') {
            $message = $fallback;
        }
        $token = (string) config('services.mercadopago.access_token');
        if ($token !== '') {
            $message = str_replace($token, '', $message);
        }

        return mb_substr(trim($message), 0, 180);
    }

    private function enabled(): bool
    {
        return filter_var(config('services.mercadopago.enabled'), FILTER_VALIDATE_BOOLEAN);
    }

    private function soles(int $cents): float
    {
        return intdiv($cents, 100) + (($cents % 100) / 100);
    }

    private function cents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    private function numericId(string $reference): string
    {
        if (preg_match('/^mp_(\d{1,20})$/', $reference, $match) !== 1) {
            throw new MercadoPagoException(__('refunds.not_card'));
        }

        return $match[1];
    }

    private function guardId(string $id): void
    {
        if (preg_match('/^\d{1,20}$/', $id) !== 1) {
            throw new MercadoPagoException(__('payments.mp_rejected'));
        }
    }

    private function organizationId(Reservation $reservation): ?int
    {
        $reservation->loadMissing('departure.transportRoute', 'airDeparture.airRoute');
        $id = $reservation->departure?->transportRoute?->organization_id
            ?? $reservation->airDeparture?->airRoute?->organization_id;

        return $id === null ? null : (int) $id;
    }
}
