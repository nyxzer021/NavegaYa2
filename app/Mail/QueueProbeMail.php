<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QueueProbeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Prueba de la cola de correo');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.queue-probe');
    }
}
