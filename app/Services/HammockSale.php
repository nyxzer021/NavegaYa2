<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\ReservationSeat;
use App\Models\RouteDeparture;
use App\Models\VesselSeat;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HammockSale
{
    /**
     * Lock the departure before choosing the first free hammock.
     *
     * @param  array{name: string, email: string, phone: string, document?: string|null, document_type?: string|null, age?: int|null, channel?: string|null}  $contact
     */
    public function reserve(RouteDeparture $departure, array $contact): Reservation
    {
        return DB::transaction(function () use ($departure, $contact): Reservation {
            $locked = RouteDeparture::query()->whereKey($departure->id)->lockForUpdate()->firstOrFail();
            $this->releaseExpired($locked);

            $taken = ReservationSeat::query()->where('route_departure_id', $locked->id)->pluck('vessel_seat_id');
            $hammock = VesselSeat::query()
                ->where('vessel_id', $locked->vessel_id)
                ->where('space_type', 'hammock')
                ->where('is_available', true)
                ->when($taken->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $taken))
                ->orderBy('code')
                ->first();

            if ($hammock === null) {
                throw new DomainException('No quedan hamacas libres en esta salida.');
            }

            $code = 'NY-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
            $reservation = Reservation::create([
                'user_id' => auth()->id(),
                'route_departure_id' => $locked->id,
                'code' => $code,
                'contact_name' => $contact['name'],
                'contact_email' => $contact['email'],
                'contact_phone' => $contact['phone'],
                'contact_document' => $contact['document'] ?? null,
                'total_amount' => $locked->fare,
                'status' => 'pending_payment',
                'expires_at' => array_key_exists('expires_at', $contact) ? $contact['expires_at'] : now()->addMinutes(10),
            ] + (isset($contact['channel']) ? ['sales_channel' => $contact['channel']] : []));
            ReservationSeat::create([
                'reservation_id' => $reservation->id,
                'route_departure_id' => $locked->id,
                'vessel_seat_id' => $hammock->id,
                'passenger_name' => $contact['name'],
                'document_type' => $contact['document_type'] ?? 'DNI',
                'document_number' => $contact['document'] ?? 'Sin documento',
                'passenger_age' => $contact['age'] ?? null,
            ]);

            return $reservation;
        });
    }

    private function releaseExpired(RouteDeparture $departure): void
    {
        $expiredIds = Reservation::query()
            ->where('route_departure_id', $departure->id)
            ->where('status', 'pending_payment')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->pluck('id');

        if ($expiredIds->isEmpty()) {
            return;
        }

        ReservationSeat::query()->whereIn('reservation_id', $expiredIds)->delete();
        Reservation::query()->whereIn('id', $expiredIds)->update(['status' => 'expired']);
    }
}
