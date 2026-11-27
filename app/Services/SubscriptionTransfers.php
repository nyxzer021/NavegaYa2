<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPaymentRequest;
use App\Models\SubscriptionPlan;
use App\Support\AuditRecorder;
use App\Support\StoredFiles;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pago de planes por transferencia, Yape o Plin: el negocio sube su comprobante
 * y NavegaYA lo aprueba (activa el plan) o lo rechaza con un motivo.
 */
class SubscriptionTransfers
{
    public function __construct(private readonly SubscriptionPaymentService $payments) {}

    /**
     * @param  array{method: string, months: int, operation_number: string, paid_on: string}  $data
     */
    public function request(Request $request, Subscription $subscription, array $data, UploadedFile $voucher): SubscriptionPaymentRequest
    {
        $plan = $this->planFor($subscription);
        $months = (int) $data['months'];

        $created = DB::transaction(function () use ($request, $subscription, $data, $voucher, $plan, $months): SubscriptionPaymentRequest {
            Subscription::query()->whereKey($subscription->id)->lockForUpdate()->firstOrFail();
            if (SubscriptionPaymentRequest::query()->where('subscription_id', $subscription->id)->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['voucher' => 'Ya enviaste un comprobante. NavegaYA lo está revisando.']);
            }

            return SubscriptionPaymentRequest::query()->create([
                'organization_id' => $subscription->organization_id,
                'subscription_id' => $subscription->id,
                'subscription_plan_id' => $plan->id,
                'months' => $months,
                'amount' => number_format((float) $plan->monthly_price * $months, 2, '.', ''),
                'method' => $data['method'],
                'operation_number' => trim($data['operation_number']),
                'paid_on' => $data['paid_on'],
                'voucher_path' => StoredFiles::storeDocument($voucher, 'comprobantes-de-planes/'.$subscription->organization_id),
                'status' => 'pending',
                'requested_by_user_id' => $request->user()?->id,
            ]);
        });

        AuditRecorder::record($request, 'subscription.transfer_requested', $created, [
            'plan' => $plan->name, 'months' => $months, 'amount' => (string) $created->amount, 'operation' => $created->operation_number,
        ], $subscription->organization_id);

        return $created;
    }

    public function approve(Request $request, SubscriptionPaymentRequest $paymentRequest): Subscription
    {
        return DB::transaction(function () use ($request, $paymentRequest): Subscription {
            $locked = SubscriptionPaymentRequest::query()->whereKey($paymentRequest->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['payment_request' => 'Este comprobante ya fue revisado.']);
            }
            $subscription = $locked->subscription()->firstOrFail();
            if ((int) $subscription->subscription_plan_id !== (int) $locked->subscription_plan_id) {
                $plan = SubscriptionPlan::query()->findOrFail($locked->subscription_plan_id);
                $subscription->update([
                    'subscription_plan_id' => $plan->id, 'monthly_price' => $plan->monthly_price,
                    'currency' => $plan->currency, 'benefits_snapshot' => $plan->benefits,
                ]);
            }
            $reference = 'TRF-'.$locked->id;
            $activated = $this->payments->confirm($subscription, [
                'method' => $locked->method,
                'provider' => 'manual',
                'provider_reference' => $reference,
                'provider_payload' => ['operation_number' => $locked->operation_number, 'paid_on' => $locked->paid_on?->toDateString(), 'request_id' => $locked->id],
            ], (int) $locked->months);
            $locked->update([
                'status' => 'approved',
                'reviewed_by_user_id' => $request->user()?->id,
                'reviewed_at' => now(),
                'payment_id' => Payment::query()->where('provider_reference', $reference)->value('id'),
            ]);
            AuditRecorder::record($request, 'subscription.transfer_approved', $locked, [
                'amount' => (string) $locked->amount, 'months' => (int) $locked->months,
                'operation' => $locked->operation_number, 'ends_on' => $activated->ends_on?->toDateString(),
            ], $locked->organization_id);

            return $activated;
        });
    }

    public function reject(Request $request, SubscriptionPaymentRequest $paymentRequest, string $reason): void
    {
        DB::transaction(function () use ($request, $paymentRequest, $reason): void {
            $locked = SubscriptionPaymentRequest::query()->whereKey($paymentRequest->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['payment_request' => 'Este comprobante ya fue revisado.']);
            }
            $locked->update([
                'status' => 'rejected',
                'rejection_reason' => $reason,
                'reviewed_by_user_id' => $request->user()?->id,
                'reviewed_at' => now(),
            ]);
            AuditRecorder::record($request, 'subscription.transfer_rejected', $locked, [
                'operation' => $locked->operation_number, 'reason' => $reason,
            ], $locked->organization_id);
        });
    }

    private function planFor(Subscription $subscription): SubscriptionPlan
    {
        return $this->payments->renewalPlan($subscription);
    }
}
