<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrganizationActivationInvitation extends Notification
{
    public function __construct(private string $url) {}

    public function via(object $n): array
    {
        return ['mail'];
    }

    public function toMail(object $n): MailMessage
    {
        return (new MailMessage)->subject('Activa tu empresa en NavegaYA')->line('Tu empresa fue aprobada.')->action('Crear contraseña y activar cuenta', $this->url)->line('El enlace vence en 3 días.');
    }
}
