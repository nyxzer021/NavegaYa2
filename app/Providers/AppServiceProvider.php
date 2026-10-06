<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\CargoShipment;
use App\Models\Organization;
use App\Models\Port;
use App\Models\RouteDeparture;
use App\Models\Ticket;
use App\Models\TransportRoute;
use App\Models\Vessel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        foreach ([Organization::class, Port::class, Vessel::class, TransportRoute::class, RouteDeparture::class, CargoShipment::class, Ticket::class] as $class) {
            $class::created(fn (Model $m) => $this->audit('created', $m, $m->getAttributes()));
            $class::updated(fn (Model $m) => $this->audit('updated', $m, $m->getChanges()));
            $class::deleted(fn (Model $m) => $this->audit('deleted', $m, []));
        }
    }

    private function audit(string $event, Model $model, array $changes): void
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return;
        }AuditLog::create(['user_id' => Auth::id(), 'event' => $event, 'subject_type' => $model::class, 'subject_id' => $model->getKey(), 'changes' => $changes, 'ip_address' => request()?->ip(), 'user_agent' => substr((string) request()?->userAgent(), 0, 500), 'created_at' => now()]);
    }
}
