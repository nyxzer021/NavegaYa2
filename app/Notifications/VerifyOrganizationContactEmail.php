<?php

namespace App\Notifications;

use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyOrganizationContactEmail extends Notification
{
    use Queueable;

    public function __construct(private Organization $organization, private string $url) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Verifica tu correo para NavegaYA')
            ->greeting('Hola '.$this->organization->contact_name.',')
            ->line('Recibimos la solicitud de '.$this->organization->legal_name.'.')
            ->line('Verifica este correo para continuar con la revisión del RUC y los datos legales.')
            ->action('Verificar correo', $this->url)
            ->line('La empresa no podrá iniciar sesión ni publicar información hasta que NavegaYA la apruebe.');
    }
}
