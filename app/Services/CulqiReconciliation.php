<?php

namespace App\Services;

use App\Models\CulqiSettlementImport;
use App\Models\CulqiSettlementLine;
use App\Models\Payment;
use App\Models\ReconciliationReview;
use App\Models\TicketRefund;
use App\Models\User;
use App\Support\AuditRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CulqiReconciliation
{
    /**
     * @return array{created: int, existing: int, duplicate_file: bool}
     */
    public function importFile(string $contents, string $filename, User $user, Request $request): array
    {
        $hash = hash('sha256', $contents);
        if (CulqiSettlementImport::query()->where('file_hash', $hash)->exists()) {
            return ['created' => 0, 'existing' => 0, 'duplicate_file' => true];
        }
        $rows = $this->parse($contents);

        return $this->store($rows, 'file', $hash, $this->filename($filename), $user, $request);
    }

    /**
     * @param  list<array{id: string, amount: string, date: string, fee: ?string}>  $charges
     * @return array{created: int, existing: int, duplicate_file: bool}
     */
    public function importCharges(array $charges, User $user, Request $request): array
    {
        $rows = [];
        foreach ($charges as $charge) {
            $rows[] = [
                'kind' => 'charge',
                'reference' => $charge['id'],
                'date' => $charge['date'],
                'amount' => $charge['amount'],
                'fee' => $charge['fee'],
                'net' => null,
            ];
        }

        return $this->store($rows, 'api', null, null, $user, $request);
    }

    /**
     * @param  list<array{kind: string, id: string, amount: string, date: string, fee: ?string, net: ?string}>  $movements
     * @return array{created: int, existing: int, duplicate_file: bool}
     */
    public function importMovements(array $movements, User $user, Request $request): array
    {
        $rows = [];
        foreach ($movements as $movement) {
            if (! in_array($movement['kind'], ['charge', 'refund'], true)) {
                continue;
            }
            $rows[] = [
                'kind' => $movement['kind'],
                'reference' => $movement['id'],
                'date' => $movement['date'],
                'amount' => $movement['amount'],
                'fee' => $movement['fee'],
                'net' => $movement['net'],
            ];
        }

        return $this->store($rows, 'api', null, null, $user, $request);
    }

    /**
     * @return array{month: string, summary: array<string, string>, days: list<array<string, string>>, differences: list<array<string, mixed>>}
     */
    public function month(string $month): array
    {
        [$start, $end] = $this->bounds($month);
        $payments = Payment::query()
            ->whereIn('status', ['confirmed', 'refunded', 'succeeded'])
            ->whereBetween('paid_at', [$start, $end])
            ->where(function ($query): void {
                $query->where(function ($culqi): void {
                    $culqi->where('provider', 'culqi')->where('provider_reference', 'like', 'chr_%');
                })->orWhere(function ($mercadoPago): void {
                    $mercadoPago->where('provider', 'mercadopago')
                        ->where('provider_reference', 'like', 'mp_%')
                        ->where('provider_reference', 'not like', 'mpr_%');
                });
            })
            ->get();
        $refunds = TicketRefund::query()
            ->where('status', 'paid')
            ->whereNotNull('provider_refund_id')
            ->whereBetween('paid_at', [$start, $end])
            ->whereHas('payment', fn ($query) => $query->whereIn('provider', ['culqi', 'mercadopago']))
            ->get();
        $lines = CulqiSettlementLine::query()
            ->whereBetween('occurred_on', [$start->toDateString(), $end->toDateString()])
            ->get();

        $localCharges = [];
        foreach ($payments as $payment) {
            $localCharges[(string) $payment->provider_reference] = $payment;
        }
        $localRefunds = [];
        foreach ($refunds as $refund) {
            $localRefunds[(string) $refund->provider_refund_id] = $refund;
        }
        $culqiCharges = [];
        $culqiRefunds = [];
        foreach ($lines as $line) {
            if ($line->kind === 'refund') {
                $culqiRefunds[(string) $line->provider_reference] = $line;
            } else {
                $culqiCharges[(string) $line->provider_reference] = $line;
            }
        }

        $differences = [];
        foreach ($culqiCharges as $reference => $line) {
            $payment = $localCharges[$reference] ?? null;
            if ($payment === null) {
                $differences[] = $this->difference($this->providerKind($reference, 'culqi_charge', 'mp_charge'), $reference, $line->occurred_on->toDateString(), (string) $line->amount, null);
            } elseif ($this->cents($payment->amount) !== $this->cents($line->amount)) {
                $differences[] = $this->difference('amount', $reference, $line->occurred_on->toDateString(), (string) $line->amount, (string) $payment->amount);
            }
        }
        foreach ($localCharges as $reference => $payment) {
            if (! isset($culqiCharges[$reference])) {
                $differences[] = $this->difference($this->providerKind($reference, 'local_charge', 'mp_local_charge'), $reference, $payment->paid_at->timezone('America/Lima')->toDateString(), null, (string) $payment->amount);
            }
        }
        foreach ($culqiRefunds as $reference => $line) {
            $refund = $localRefunds[$reference] ?? null;
            if ($refund === null) {
                $differences[] = $this->difference($this->providerKind($reference, 'culqi_refund', 'mp_refund'), $reference, $line->occurred_on->toDateString(), (string) $line->amount, null);
            } elseif ($this->cents($refund->amount) !== $this->cents($line->amount)) {
                $differences[] = $this->difference('amount', $reference, $line->occurred_on->toDateString(), (string) $line->amount, (string) $refund->amount);
            }
        }
        foreach ($localRefunds as $reference => $refund) {
            if (! isset($culqiRefunds[$reference])) {
                $differences[] = $this->difference($this->providerKind($reference, 'local_refund', 'mp_local_refund'), $reference, $refund->paid_at->timezone('America/Lima')->toDateString(), null, (string) $refund->amount);
            }
        }

        $reviews = ReconciliationReview::query()
            ->whereIn('difference_key', array_column($differences, 'key'))
            ->get()
            ->keyBy('difference_key');
        foreach ($differences as $index => $difference) {
            $review = $reviews->get($difference['key']);
            $differences[$index]['reviewed'] = $review !== null;
            $differences[$index]['note'] = $review?->note;
        }
        usort($differences, fn (array $left, array $right): int => [$left['date'], $left['key']] <=> [$right['date'], $right['key']]);

        return [
            'month' => $month,
            'summary' => $this->summary($payments, $refunds, $lines),
            'days' => $this->days($payments, $refunds, $lines),
            'differences' => $differences,
        ];
    }

    public function review(Request $request, string $month, string $key, string $note): void
    {
        $known = collect($this->month($month)['differences'])->pluck('key')->all();
        if (! in_array($key, $known, true)) {
            throw ValidationException::withMessages(['note' => __('reconciliation.unknown_difference')]);
        }
        $review = ReconciliationReview::query()->updateOrCreate(
            ['difference_key' => $key],
            ['note' => $note, 'user_id' => $request->user()?->id],
        );
        AuditRecorder::record($request, 'reconcile.reviewed', $review, [
            'key' => $key,
            'note' => $note,
        ]);
    }

    /**
     * @param  list<array{kind: string, reference: string, date: string, amount: string, fee: ?string, net: ?string}>  $rows
     * @return array{created: int, existing: int, duplicate_file: bool}
     */
    private function store(array $rows, string $source, ?string $hash, ?string $filename, User $user, Request $request): array
    {
        $created = 0;
        $existing = 0;
        $import = DB::transaction(function () use ($rows, $source, $hash, $filename, $user, &$created, &$existing): CulqiSettlementImport {
            $import = CulqiSettlementImport::query()->create([
                'source' => $source,
                'file_hash' => $hash,
                'filename' => $filename,
                'user_id' => $user->id,
                'row_count' => 0,
            ]);
            $seen = [];
            foreach ($rows as $row) {
                if (isset($seen[$row['reference']]) || CulqiSettlementLine::query()->where('provider_reference', $row['reference'])->exists()) {
                    $existing++;
                    $seen[$row['reference']] = true;

                    continue;
                }
                $seen[$row['reference']] = true;
                CulqiSettlementLine::query()->create([
                    'import_id' => $import->id,
                    'kind' => $row['kind'],
                    'provider_reference' => $row['reference'],
                    'occurred_on' => $row['date'],
                    'amount' => $row['amount'],
                    'fee' => $row['fee'],
                    'net' => $row['net'],
                ]);
                $created++;
            }
            $import->update(['row_count' => $created]);

            return $import;
        });
        AuditRecorder::record($request, 'reconcile.imported', $import, [
            'source' => $source,
            'rows' => $created,
        ]);

        return ['created' => $created, 'existing' => $existing, 'duplicate_file' => false];
    }

    /**
     * @return list<array{kind: string, reference: string, date: string, amount: string, fee: ?string, net: ?string}>
     */
    private function parse(string $contents): array
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;
        $lines = preg_split('/\r\n|\n|\r/', trim($contents)) ?: [];
        $lines = array_values(array_filter($lines, fn (string $line): bool => trim($line) !== ''));
        if (count($lines) < 2) {
            throw ValidationException::withMessages(['archivo' => __('reconciliation.empty')]);
        }
        $delimiter = str_contains($lines[0], ';') && ! str_contains($lines[0], ',') ? ';' : ',';
        $header = array_map($this->header(...), str_getcsv($lines[0], $delimiter));
        $columns = array_flip($header);
        foreach (['id', 'fecha', 'tipo', 'monto'] as $required) {
            if (! array_key_exists($required, $columns)) {
                throw ValidationException::withMessages(['archivo' => __('reconciliation.bad_columns')]);
            }
        }
        $rows = [];
        foreach (array_slice($lines, 1) as $offset => $line) {
            $cells = str_getcsv($line, $delimiter);
            $number = $offset + 2;
            try {
                $rows[] = $this->row($cells, $columns);
            } catch (\InvalidArgumentException) {
                throw ValidationException::withMessages(['archivo' => __('reconciliation.bad_row', ['row' => $number])]);
            }
        }

        return $rows;
    }

    /**
     * @param  list<string|null>  $cells
     * @param  array<string, int>  $columns
     * @return array{kind: string, reference: string, date: string, amount: string, fee: ?string, net: ?string}
     */
    private function row(array $cells, array $columns): array
    {
        $value = function (string $name) use ($cells, $columns): string {
            $index = $columns[$name] ?? null;
            if ($index === null) {
                return '';
            }

            return trim((string) ($cells[$index] ?? ''));
        };
        $reference = $value('id');
        if (preg_match('/^(chr_|ref_|mp_|mpr_)[A-Za-z0-9_]+$/', $reference) !== 1) {
            throw new \InvalidArgumentException('id');
        }
        $kind = match (Str::ascii(mb_strtolower($value('tipo')))) {
            'cargo', 'venta', 'charge', 'cobro' => 'charge',
            'reembolso', 'devolucion', 'refund' => 'refund',
            default => throw new \InvalidArgumentException('tipo'),
        };
        $chargeId = str_starts_with($reference, 'chr_') || str_starts_with($reference, 'mp_');
        $refundId = str_starts_with($reference, 'ref_') || str_starts_with($reference, 'mpr_');
        if (($kind === 'charge' && ! $chargeId) || ($kind === 'refund' && ! $refundId)) {
            throw new \InvalidArgumentException('kind');
        }

        return [
            'kind' => $kind,
            'reference' => $reference,
            'date' => $this->date($value('fecha')),
            'amount' => $this->money($value('monto'), true),
            'fee' => $this->money($value('comision'), false),
            'net' => $this->money($value('neto'), false),
        ];
    }

    private function header(string $value): string
    {
        $value = Str::ascii(mb_strtolower(trim($value)));
        $value = str_replace([' ', '-'], '_', $value);

        return match ($value) {
            'codigo', 'id_cargo', 'id_de_cargo' => 'id',
            'comision_culqi' => 'comision',
            'monto_neto', 'neto_depositado' => 'neto',
            default => $value,
        };
    }

    private function date(string $value): string
    {
        $format = preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? 'Y-m-d' : (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value) === 1 ? 'd/m/Y' : '');
        if ($format === '') {
            throw new \InvalidArgumentException('date');
        }
        $date = Carbon::createFromFormat($format, $value, 'America/Lima');
        if ($date === false || $date->format($format) !== $value) {
            throw new \InvalidArgumentException('date');
        }

        return $date->toDateString();
    }

    private function money(string $value, bool $required): ?string
    {
        $value = str_replace([' ', 'S/', 's/'], '', trim($value));
        if ($value === '') {
            if ($required) {
                throw new \InvalidArgumentException('money');
            }

            return null;
        }
        if (str_contains($value, ',') && str_contains($value, '.')) {
            if (strrpos($value, ',') > strrpos($value, '.')) {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } else {
                $value = str_replace(',', '', $value);
            }
        } elseif (str_contains($value, ',')) {
            $value = str_replace(',', '.', $value);
        }
        if (preg_match('/^(\d{1,7})(?:\.(\d{1,2}))?$/', $value, $match) !== 1) {
            throw new \InvalidArgumentException('money');
        }
        $cents = ((int) $match[1]) * 100 + (int) str_pad($match[2] ?? '0', 2, '0');

        return $this->decimal($cents);
    }

    /**
     * @param  Collection<int, Payment>  $payments
     * @param  Collection<int, TicketRefund>  $refunds
     * @param  Collection<int, CulqiSettlementLine>  $lines
     * @return array<string, string>
     */
    private function summary($payments, $refunds, $lines): array
    {
        $charged = 0;
        $service = 0;
        $fares = 0;
        foreach ($payments as $payment) {
            $charged += $this->cents($payment->amount);
            $service += $this->cents($payment->commission_amount);
            $fares += $this->cents($payment->operator_net);
        }
        $refunded = 0;
        $returnedFees = 0;
        $returnedFares = 0;
        foreach ($refunds as $refund) {
            $refunded += $this->cents($refund->amount);
            $returnedFees += $this->cents($refund->fee_amount);
            $returnedFares += $this->cents($refund->fare_amount);
        }
        $culqiFee = 0;
        $deposited = 0;
        foreach ($lines as $line) {
            $fee = $this->cents($line->fee ?? 0);
            $net = $this->cents($line->net ?? $line->amount);
            if ($line->kind === 'refund') {
                $culqiFee -= $fee;
                $deposited -= $net;
            } else {
                $culqiFee += $fee;
                $deposited += $net;
            }
        }

        return [
            'charged' => $this->decimal($charged),
            'refunded' => $this->decimal($refunded),
            'culqi_fee' => $this->decimal($culqiFee),
            'service_fee' => $this->decimal($service),
            'fares' => $this->decimal($fares - $returnedFares),
            'net_gain' => $this->decimal($service - $culqiFee - $returnedFees),
            'deposited' => $this->decimal($deposited),
        ];
    }

    /**
     * @param  Collection<int, Payment>  $payments
     * @param  Collection<int, TicketRefund>  $refunds
     * @param  Collection<int, CulqiSettlementLine>  $lines
     * @return list<array<string, string>>
     */
    private function days($payments, $refunds, $lines): array
    {
        $days = [];
        $bucket = function (string $date) use (&$days): array {
            return $days[$date] ?? ['culqi' => 0, 'local' => 0, 'culqi_refund' => 0, 'local_refund' => 0, 'fee' => 0, 'net' => 0];
        };
        foreach ($payments as $payment) {
            $date = $payment->paid_at->timezone('America/Lima')->toDateString();
            $row = $bucket($date);
            $row['local'] += $this->cents($payment->amount);
            $days[$date] = $row;
        }
        foreach ($refunds as $refund) {
            $date = $refund->paid_at->timezone('America/Lima')->toDateString();
            $row = $bucket($date);
            $row['local_refund'] += $this->cents($refund->amount);
            $days[$date] = $row;
        }
        foreach ($lines as $line) {
            $date = $line->occurred_on->toDateString();
            $row = $bucket($date);
            $fee = $this->cents($line->fee ?? 0);
            $net = $this->cents($line->net ?? $line->amount);
            if ($line->kind === 'refund') {
                $row['culqi_refund'] += $this->cents($line->amount);
                $row['fee'] -= $fee;
                $row['net'] -= $net;
            } else {
                $row['culqi'] += $this->cents($line->amount);
                $row['fee'] += $fee;
                $row['net'] += $net;
            }
            $days[$date] = $row;
        }
        ksort($days);
        $listed = [];
        foreach ($days as $date => $row) {
            $listed[] = [
                'date' => $date,
                'culqi' => $this->decimal($row['culqi']),
                'local' => $this->decimal($row['local']),
                'culqi_refund' => $this->decimal($row['culqi_refund']),
                'local_refund' => $this->decimal($row['local_refund']),
                'fee' => $this->decimal($row['fee']),
                'net' => $this->decimal($row['net']),
            ];
        }

        return $listed;
    }

    /**
     * @return array{key: string, kind: string, reference: string, date: string, culqi: ?string, local: ?string, reviewed: bool, note: ?string}
     */
    private function difference(string $kind, string $reference, string $date, ?string $culqi, ?string $local): array
    {
        return [
            'key' => $kind.':'.$reference,
            'kind' => $kind,
            'reference' => $reference,
            'date' => $date,
            'culqi' => $culqi,
            'local' => $local,
            'reviewed' => false,
            'note' => null,
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function bounds(string $month): array
    {
        $start = Carbon::createFromFormat('Y-m-d', $month.'-01', 'America/Lima')->startOfMonth();

        return [$start->clone()->utc(), $start->clone()->endOfMonth()->utc()];
    }

    private function providerKind(string $reference, string $culqiKind, string $mercadoPagoKind): string
    {
        return str_starts_with($reference, 'mp') ? $mercadoPagoKind : $culqiKind;
    }

    private function cents(mixed $amount): int
    {
        $normal = number_format((float) $amount, 2, '.', '');
        $negative = str_starts_with($normal, '-');
        [$whole, $fraction] = explode('.', ltrim($normal, '-'));
        $cents = ((int) $whole) * 100 + (int) $fraction;

        return $negative ? -$cents : $cents;
    }

    private function decimal(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);

        return $sign.intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    private function filename(string $filename): string
    {
        $name = basename(str_replace('\\', '/', $filename));
        $name = preg_replace('/[^A-Za-z0-9._-]/', '', $name) ?? '';

        return mb_substr($name === '' ? 'liquidacion.csv' : $name, 0, 120);
    }
}
