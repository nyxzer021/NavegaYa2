<?php

namespace App\Http\Controllers;

use App\Models\CargoShipment;
use App\Support\DepartureSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class CargoTrackingController extends Controller
{
    public function show(Request $request): View
    {
        return view('cargo.track', [
            'code' => strtoupper($request->string('codigo')->toString()),
            'shipment' => null,
        ]);
    }

    public function lookup(Request $request): View|RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'document' => ['required', 'string', 'max:40'],
        ], [
            'code.required' => __('tracking.miss'),
            'document.required' => __('tracking.miss'),
        ]);

        $code = strtoupper(trim($data['code']));
        if ($this->blocked($code)) {
            return back()->withInput()->withErrors(['code' => __('tracking.miss')]);
        }

        $shipment = CargoShipment::query()
            ->with([
                'departure.transportRoute.originPort',
                'departure.transportRoute.destinationPort',
                'departure.vessel',
                'events' => fn ($query) => $query->orderBy('happened_at'),
            ])
            ->where('code', $code)
            ->first();

        if ($shipment === null || ! $this->documentMatches($shipment, $data['document'])) {
            $this->rememberFailure($code);

            return back()->withInput()->withErrors(['code' => __('tracking.miss')]);
        }

        return view('cargo.track', [
            'code' => $shipment->code,
            'shipment' => $shipment,
            'senderDocument' => $this->mask((string) $shipment->sender_document),
            'recipientDocument' => $this->mask((string) $shipment->recipient_document),
        ]);
    }

    private function documentMatches(CargoShipment $shipment, string $given): bool
    {
        return $this->same($shipment->sender_document, $given) || $this->same($shipment->recipient_document, $given);
    }

    private function same(?string $stored, string $given): bool
    {
        $left = $this->normalize((string) $stored);
        $right = $this->normalize($given);

        return $left !== '' && strlen($left) === strlen($right) && hash_equals($left, $right);
    }

    private function normalize(string $value): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]/', '', $value) ?? '');
    }

    private function mask(string $document): string
    {
        $clean = preg_replace('/\s+/', '', $document) ?? '';
        $tail = substr($clean, -4);

        return '****'.($tail !== '' ? $tail : '');
    }

    private function failureKey(string $code): string
    {
        $hour = now()->timezone(DepartureSchedule::ZONE)->format('YmdH');

        return 'cargo-track-fails:'.$code.':'.$hour;
    }

    private function blocked(string $code): bool
    {
        return (int) Cache::get($this->failureKey($code), 0) >= 5;
    }

    private function rememberFailure(string $code): void
    {
        $key = $this->failureKey($code);
        Cache::put($key, (int) Cache::get($key, 0) + 1, now()->addHour());
    }
}
