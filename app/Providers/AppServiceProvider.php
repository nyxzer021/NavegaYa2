<?php

namespace App\Providers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Event::listen('eloquent.created: *', fn (string $event, array $models) => $this->capture('created', $models[0] ?? null));
        Event::listen('eloquent.updated: *', fn (string $event, array $models) => $this->capture('updated', $models[0] ?? null));
        Event::listen('eloquent.deleted: *', fn (string $event, array $models) => $this->capture('deleted', $models[0] ?? null));
    }

    private function capture(string $event, mixed $subject): void
    {
        if (! $subject instanceof Model || $subject instanceof AuditLog || ! str_starts_with($subject::class, 'App\\Models\\')) {
            return;
        }

        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return;
        }

        $changes = match ($event) {
            'created' => ['new' => $this->safe($subject->getAttributes())],
            'updated' => ['old' => $this->safe(array_intersect_key($subject->getOriginal(), $subject->getChanges())), 'new' => $this->safe($subject->getChanges())],
            'deleted' => ['old' => $this->safe($subject->getOriginal())],
        };

        AuditLog::create([
            'user_id' => Auth::id(), 'event' => $event, 'subject_type' => $subject::class, 'subject_id' => $subject->getKey(),
            'changes' => $changes, 'ip_address' => request()?->ip(), 'user_agent' => substr((string) request()?->userAgent(), 0, 500),
            'route_name' => request()?->route()?->getName(), 'http_method' => request()?->method(), 'created_at' => now(),
        ]);
    }

    private function safe(array $values): array
    {
        $sensitive = ['password', 'remember_token', 'token', 'secret', 'api_key', 'cvv', 'card_number'];

        return collect($values)->reject(fn (mixed $value, string $key) => collect($sensitive)->contains(fn (string $word) => str_contains(strtolower($key), $word)))->all();
    }
}
