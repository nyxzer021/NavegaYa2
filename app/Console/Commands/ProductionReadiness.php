<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ProductionReadiness extends Command
{
    protected $signature = 'navegaya:production-readiness';

    protected $description = 'Verifica que el entorno tenga la configuración mínima antes de publicar';

    public function handle(): int
    {
        $failures = [];
        $filesystem = (string) config('filesystems.default');
        $mailFrom = (string) config('mail.from.address');
        $checks = [
            'APP_ENV=production' => app()->environment('production'),
            'APP_DEBUG=false' => ! config('app.debug'),
            'APP_URL usa HTTPS' => str_starts_with((string) config('app.url'), 'https://'),
            'Cookie de sesión segura' => (bool) config('session.secure'),
            'Sesión cifrada' => (bool) config('session.encrypt'),
            'Cola asíncrona' => config('queue.default') !== 'sync',
            'Correo transaccional configurado' => config('mail.default') !== 'log',
            'Remitente de correo real' => $mailFrom !== '' && ! str_ends_with($mailFrom, '@example.com'),
            'Base de datos persistente' => config('database.default') !== 'sqlite',
            'Pagos simulados desactivados' => ! config('payments.sandbox_enabled'),
            'Archivos en almacenamiento persistente' => ! in_array($filesystem, ['local', 'public'], true),
            'Respaldos automáticos activos' => (bool) config('operations.backups.enabled'),
        ];

        foreach ($checks as $label => $passed) {
            if ($passed) {
                $this->components->twoColumnDetail($label, '<fg=green>OK</>');
            } else {
                $this->components->twoColumnDetail($label, '<fg=red>PENDIENTE</>');
                $failures[] = $label;
            }
        }

        if (config('services.culqi.enabled')) {
            $culqiReady = config('services.culqi.environment') === 'production'
                && config('services.culqi.public_key')
                && config('services.culqi.secret_key')
                && config('services.culqi.webhook_secret');
            $this->components->twoColumnDetail('Culqi de producción', $culqiReady ? '<fg=green>CONFIGURADO</>' : '<fg=red>INCOMPLETO</>');
            if (! $culqiReady) {
                $failures[] = 'Culqi (entorno, claves y webhook)';
            }
        } else {
            $this->components->twoColumnDetail('Culqi', '<fg=yellow>PENDIENTE (no bloquea la publicación)</>');
        }

        if (config('services.google.enabled')) {
            $googleReady = config('services.google.client_id') && config('services.google.client_secret') && config('services.google.redirect');
            $this->components->twoColumnDetail('Google', $googleReady ? '<fg=green>CONFIGURADO</>' : '<fg=red>INCOMPLETO</>');
            if (! $googleReady) {
                $failures[] = 'Credenciales de Google';
            }
        }

        if ($failures) {
            $this->newLine();
            $this->components->error('No publiques todavía. Revisa: '.implode(', ', $failures).'.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info('El entorno cumple la configuración base de publicación.');

        return self::SUCCESS;
    }
}
