<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateSuperAdmin extends Command
{
    protected $signature = 'navegaya:create-super-admin';

    protected $description = 'Crea el primer administrador principal de NavegaYA';

    public function handle(): int
    {
        $name = $this->ask('Nombre completo');
        $email = $this->ask('Correo del administrador');
        $password = $this->secret('Contraseña');

        if (User::where('email', $email)->exists()) {
            $this->error('Ya existe un usuario con ese correo.');

            return self::FAILURE;
        }

        $user = User::create(['name' => $name, 'email' => $email, 'password' => Hash::make($password), 'email_verified_at' => now()]);
        Role::where('code', 'super_admin')->firstOrFail()->users()->attach($user->id);
        $this->info('Administrador principal creado correctamente.');

        return self::SUCCESS;
    }
}
