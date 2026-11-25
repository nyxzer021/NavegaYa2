<?php

namespace App\Services;

use App\Mail\ConsumerClaimAnsweredMail;
use App\Mail\ConsumerClaimFiledMail;
use App\Models\ConsumerClaim;
use App\Models\ConsumerClaimSequence;
use App\Models\Reservation;
use App\Models\User;
use App\Support\AuditRecorder;
use App\Support\DepartureSchedule;
use App\Support\LimaBusinessCalendar;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ConsumerClaims
{
    public function __construct(private readonly LimaBusinessCalendar $calendar) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function file(array $data): ConsumerClaim
    {
        $claim = null;
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $claim = DB::transaction(fn (): ConsumerClaim => $this->insert($data));
                break;
            } catch (UniqueConstraintViolationException) {
                if ($attempt === 2) {
                    throw ValidationException::withMessages(['provider' => __('claims.busy')]);
                }
            }
        }

        $this->send(fn () => Mail::to($claim->email)->send(new ConsumerClaimFiledMail($claim)));

        return $claim;
    }

    public function answer(ConsumerClaim $claim, User $user, string $response): ConsumerClaim
    {
        $claim = DB::transaction(function () use ($claim, $user, $response): ConsumerClaim {
            $locked = ConsumerClaim::query()->whereKey($claim->id)->lockForUpdate()->firstOrFail();
            if ($locked->answered()) {
                throw ValidationException::withMessages(['response' => __('claims.locked')]);
            }
            $locked->update([
                'response_text' => $response,
                'responded_at' => now(),
                'responded_by_user_id' => $user->id,
            ]);
            AuditRecorder::record(request(), 'claim.answered', $locked, [
                'code' => $locked->code,
            ], $locked->organization_id);

            return $locked;
        });

        $this->send(fn () => Mail::to($claim->email)->send(new ConsumerClaimAnsweredMail($claim)));

        return $claim;
    }

    public function organizationId(Reservation $reservation): ?int
    {
        $reservation->loadMissing(['departure.transportRoute', 'airDeparture.airRoute']);

        return $reservation->departure?->transportRoute?->organization_id
            ?? $reservation->airDeparture?->airRoute?->organization_id;
    }

    /**
     * @return array<string, mixed>
     */
    public function sheet(ConsumerClaim $claim): array
    {
        $claim->loadMissing('organization');
        $platform = $claim->provider === 'platform';

        return [
            'claim' => $claim,
            'provider_name' => $platform ? __('claims.platform_legal_name') : (string) $claim->organization?->legal_name,
            'provider_ruc' => $platform ? __('claims.platform_ruc') : (string) ($claim->organization?->ruc ?: '—'),
            'date' => $claim->filed_on->format('d/m/Y'),
            'due' => $claim->due_on->format('d/m/Y'),
            'amount' => 'S/ '.number_format((float) $claim->amount, 2, '.', ''),
            'type' => __('claims.type_'.$claim->claim_type),
            'good' => __('claims.good_'.$claim->good_kind),
            'document' => __('claims.document_'.$claim->document_type).' '.$claim->document_number,
            'guardian_role' => $claim->guardian_relationship ? __('claims.relationship_'.$claim->guardian_relationship) : '',
            'countdown' => $claim->countdown(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function insert(array $data): ConsumerClaim
    {
        $year = (int) now()->timezone(DepartureSchedule::ZONE)->format('Y');
        $filed = now()->timezone(DepartureSchedule::ZONE)->toDateString();
        $providerKey = $data['provider'] === 'platform' ? 'platform' : 'company:'.$data['organization_id'];
        $sequence = $this->next($providerKey, $year);
        $due = $this->calendar->dueOn(Carbon::parse($filed, DepartureSchedule::ZONE));

        return ConsumerClaim::query()->create([
            'provider' => $data['provider'],
            'provider_key' => $providerKey,
            'organization_id' => $data['provider'] === 'company' ? $data['organization_id'] : null,
            'reservation_id' => $data['reservation_id'] ?? null,
            'reservation_code' => $data['reservation_code'] ?? null,
            'series_year' => $year,
            'sequence' => $sequence,
            'code' => sprintf('RN-%d-%06d', $year, $sequence),
            'public_token' => Str::random(40),
            'filed_on' => $filed,
            'due_on' => $due->toDateString(),
            'consumer_name' => $data['consumer_name'],
            'document_type' => $data['document_type'],
            'document_number' => $data['document_number'],
            'address' => $data['address'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'is_minor' => (bool) ($data['is_minor'] ?? false),
            'guardian_name' => $data['guardian_name'] ?? null,
            'guardian_document' => $data['guardian_document'] ?? null,
            'guardian_relationship' => $data['guardian_relationship'] ?? null,
            'good_kind' => $data['good_kind'],
            'amount' => $data['amount'],
            'good_description' => $data['good_description'],
            'claim_type' => $data['claim_type'],
            'detail' => $data['detail'],
            'request_text' => $data['request_text'],
            'email_accepted' => true,
        ]);
    }

    private function next(string $providerKey, int $year): int
    {
        $now = now();
        ConsumerClaimSequence::query()->insertOrIgnore([
            'provider_key' => $providerKey,
            'series_year' => $year,
            'last_number' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $counter = ConsumerClaimSequence::query()
            ->where('provider_key', $providerKey)
            ->where('series_year', $year)
            ->lockForUpdate()
            ->firstOrFail();
        $sequence = (int) $counter->last_number + 1;
        $counter->update(['last_number' => $sequence]);

        return $sequence;
    }

    private function send(callable $delivery): void
    {
        try {
            $delivery();
        } catch (\Throwable) {
            // La constancia ya quedó guardada. El correo en log o caído no borra el reclamo.
        }
    }
}
