<?php

namespace App\Support;

use App\Models\Payment;
use Illuminate\Validation\ValidationException;

/**
 * Única regla del cargo de servicio.
 * Si la empresa cancela la salida, se devuelve el pasaje y el cargo.
 * Si el pasajero se arrepiente, el cargo no se devuelve.
 */
class RefundRule
{
    public const PASSENGER = 'passenger';

    public const COMPANY_CANCEL = 'company_cancel';

    public const CANCEL_RULE = 'La empresa canceló la salida: se devuelve todo, incluido el cargo de servicio.';

    public const REGRET_RULE = 'El pasajero se arrepintió: el cargo de servicio no se devuelve.';

    /**
     * @return array{fare_cents: int, fee_cents: int, total_cents: int, percent: ?string, explanation: string, needs_amount: bool, cap_cents: int}
     */
    public function quote(Payment $payment, string $cause, ?string $policyPercent, ?string $typedAmount): array
    {
        $paid = $this->cents((string) $payment->amount);
        $fare = $this->cents((string) ($payment->operator_net ?? '0'));
        $fee = $this->cents((string) ($payment->commission_amount ?? '0'));
        if ($fare === 0 && $fee === 0) {
            $fare = $paid;
        }

        if ($cause === self::COMPANY_CANCEL) {
            $fareBack = $fare;
            $feeBack = $fee;
            if ($fareBack + $feeBack > $paid) {
                $feeBack = max(0, $feeBack - (($fareBack + $feeBack) - $paid));
                $fareBack = max(0, $paid - $feeBack);
            }

            return [
                'fare_cents' => $fareBack,
                'fee_cents' => $feeBack,
                'total_cents' => $fareBack + $feeBack,
                'percent' => '100.00',
                'explanation' => self::CANCEL_RULE.' Pasaje '.$this->soles($fareBack).' y cargo '.$this->soles($feeBack).', total '.$this->soles($fareBack + $feeBack).'.',
                'needs_amount' => false,
                'cap_cents' => $paid,
            ];
        }

        $cap = $fee > 0 ? min($fare, $paid) : ($fare > 0 ? min($fare, $paid) : $paid);
        $percent = $this->percent($policyPercent);
        if ($percent !== null) {
            $back = (int) round($cap * $percent / 100);

            return $this->passenger($back, $cap, number_format($percent, 2, '.', ''), self::REGRET_RULE.' La política devuelve el '.$this->percentLabel($percent).'% del pasaje ('.$this->soles($back).'). El máximo era '.$this->soles($cap).'.');
        }

        if (blank($typedAmount)) {
            return [
                'fare_cents' => 0,
                'fee_cents' => 0,
                'total_cents' => 0,
                'percent' => null,
                'explanation' => self::REGRET_RULE.' La política no define un porcentaje. El máximo es '.$this->soles($cap).'.',
                'needs_amount' => true,
                'cap_cents' => $cap,
            ];
        }

        $back = $this->cents($typedAmount);
        if ($back < 1) {
            throw ValidationException::withMessages(['amount' => __('refunds.amount_required')]);
        }
        if ($back > $cap) {
            throw ValidationException::withMessages(['amount' => __('refunds.above')]);
        }

        return $this->passenger($back, $cap, null, self::REGRET_RULE.' La empresa indicó '.$this->soles($back).'. El máximo era '.$this->soles($cap).'.');
    }

    /**
     * @return array{fare_cents: int, fee_cents: int, total_cents: int, percent: ?string, explanation: string, needs_amount: bool, cap_cents: int}
     */
    private function passenger(int $back, int $cap, ?string $percent, string $explanation): array
    {
        return [
            'fare_cents' => $back,
            'fee_cents' => 0,
            'total_cents' => $back,
            'percent' => $percent,
            'explanation' => $explanation,
            'needs_amount' => false,
            'cap_cents' => $cap,
        ];
    }

    private function percent(?string $policyPercent): ?float
    {
        if ($policyPercent === null || $policyPercent === '') {
            return null;
        }

        return round((float) $policyPercent, 2);
    }

    private function percentLabel(float $percent): string
    {
        return fmod($percent, 1.0) === 0.0 ? (string) (int) $percent : number_format($percent, 2, '.', '');
    }

    private function cents(string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function soles(int $cents): string
    {
        return 'S/ '.number_format($cents / 100, 2, '.', '');
    }
}
