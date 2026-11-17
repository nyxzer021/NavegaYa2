<?php

namespace App\Http\Controllers;

use App\Models\CargoShipment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $shipment = CargoShipment::query()
            ->with([
                'departure.transportRoute.originPort',
                'departure.transportRoute.destinationPort',
                'departure.vessel',
                'events' => fn ($query) => $query->orderBy('happened_at'),
            ])
            ->where('code', strtoupper(trim($data['code'])))
            ->first();

        if ($shipment === null || ! $this->documentMatches($shipment, $data['document'])) {
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
}
