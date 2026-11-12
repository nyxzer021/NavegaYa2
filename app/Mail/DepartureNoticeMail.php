<?php

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DepartureNoticeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Reservation $reservation, public string $noticeReason, public string $change) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Cambio en tu salida · NavegaYA');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.departure-notice');
    }
}
