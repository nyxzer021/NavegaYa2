<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CulqiException;
use App\Services\CulqiGateway;
use App\Support\PaymentConnections;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminPaymentConnectionController extends Controller
{
    public function index(): View
    {
        return view('admin.payment-connections.index', [
            'culqi' => $this->culqi(),
            'webhookUrl' => route('webhooks.culqi'),
            'lastWebhook' => PaymentConnections::lastCulqiWebhook()?->timezone('America/Lima')->format('Y-m-d H:i'),
        ]);
    }

    public function probe(Request $request, CulqiGateway $culqi): RedirectResponse
    {
        if ($request->string('provider')->toString() !== 'culqi') {
            return back()->with('probe_error', __('connections.provider_off'));
        }
        if (! (bool) config('services.culqi.enabled') || ! filled(config('services.culqi.secret_key'))) {
            return back()->with('probe_error', __('connections.culqi_off'));
        }
        try {
            $culqi->probe();
        } catch (CulqiException $exception) {
            return back()->with('probe_error', $exception->getMessage());
        }

        return back()->with('probe_ok', __('connections.probe_ok'));
    }

    /**
     * @return array{enabled: bool, test: bool, public_present: bool, public_tail: ?string, secret_present: bool, secret_tail: ?string, webhook_present: bool, webhook_tail: ?string}
     */
    private function culqi(): array
    {
        return [
            'enabled' => (bool) config('services.culqi.enabled'),
            'test' => (string) config('services.culqi.environment') !== 'production',
            'public_present' => filled(config('services.culqi.public_key')),
            'public_tail' => PaymentConnections::tail((string) config('services.culqi.public_key')),
            'secret_present' => filled(config('services.culqi.secret_key')),
            'secret_tail' => PaymentConnections::tail((string) config('services.culqi.secret_key')),
            'webhook_present' => filled(config('services.culqi.webhook_secret')),
            'webhook_tail' => PaymentConnections::tail((string) config('services.culqi.webhook_secret')),
        ];
    }
}
