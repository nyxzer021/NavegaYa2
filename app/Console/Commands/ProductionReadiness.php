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
        $checks = [
            'APP_ENV=production' => app()->environment('production'),
            'APP_DEBUG=false' => ! config('app.debug'),
            'APP_URL usa HTTPS' => str_starts_with((string) config('app.url'), 'https://'),
            'Cookie de sesión segura' => (bool) config('session.secure'),
            'Sesión cifrada' => (bool) config('session.encrypt'),
            'Cola asíncrona' => config('queue.default') !== 'sync',
            'Correo transaccional configurado' => config('mail.default') !== 'log',
            'Respaldos automáticos activos' => (bool) config('operations.backups.enabled'),
            'Enlace público de archivos creado' => is_link(public_path('storage')),
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
            $this->components->twoColumnDetail('Culqi habilitado', config('services.culqi.secret_key') ? '<fg=green>CONFIGURADO</>' : '<fg=red>SIN CLAVE</>');
            if (! config('services.culqi.secret_key')) {
                $failures[] = 'Clave secreta de Culqi';
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
