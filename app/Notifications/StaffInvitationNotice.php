<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StaffInvitationNotice extends Notification
{
    public function __construct(private string $url, private string $company) {}

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
            ->subject(__('staff.mail_subject', ['company' => $this->company]))
            ->line(__('staff.mail_line', ['company' => $this->company]))
            ->line(__('staff.mail_password'))
            ->action(__('staff.mail_action'), $this->url)
            ->line(__('staff.mail_expires'));
    }
}
