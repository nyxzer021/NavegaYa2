<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AgencySellerInvitation extends Notification
{
    public function __construct(private string $url, private string $agency) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Invitación para vender en '.$this->agency)
            ->line('La empresa te invitó a vender los pasajes de '.$this->agency.'.')
            ->line('Tú eliges la contraseña. La empresa no la escribe.')
            ->action('Crear mi contraseña', $this->url)
            ->line('El enlace vence en 3 días.');
    }
}
