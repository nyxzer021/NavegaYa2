<?php

namespace App\Mail;

use App\Models\ConsumerClaim;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ConsumerClaimAnsweredMail extends Mailable
{
    use Queueable;

    public function __construct(public ConsumerClaim $claim) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('claims.mail_answered_subject', ['code' => $this->claim->code]));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.claims-answered');
    }
}
