<?php

namespace App\Mail;

use App\Models\Passeport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasseportDisponible extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Passeport $passeport) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Votre passeport N° {$this->passeport->numero} est disponible",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.passeport-disponible',
        );
    }
}
