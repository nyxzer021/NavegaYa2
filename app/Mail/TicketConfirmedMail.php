<?php

namespace App\Mail;

use App\Models\Reservation;
use App\Support\BoardingQr;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class TicketConfirmedMail extends Mailable
{
    use Queueable;

    public function __construct(public Reservation $reservation) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Tus boletos confirmados · NavegaYA');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.ticket-confirmed');
    }

    public function attachments(): array
    {
        $reservation = $this->reservation->loadMissing([
            'departure.transportRoute.originPort',
            'departure.transportRoute.destinationPort',
            'departure.vessel.organization',
            'airDeparture.airRoute',
            'airDeparture.aircraft.organization',
            'seats.seat',
            'seats.aircraftSeat',
            'seats.ticket',
        ]);
        $qrDataUris = [];
        foreach ($reservation->seats as $seat) {
            if ($seat->ticket) {
                $qrDataUris[$seat->ticket->id] = BoardingQr::dataUri($seat->ticket);
            }
        }

        return [Attachment::fromData(
            fn () => app('dompdf.wrapper')->loadView('mail.tickets-pdf', compact('reservation', 'qrDataUris'))->setPaper('a4')->output(),
            'boletos-'.$reservation->code.'.pdf',
        )->withMime('application/pdf')];
    }
}
