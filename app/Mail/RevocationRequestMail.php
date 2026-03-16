<?php

namespace App\Mail;

use App\Models\Event;
use App\Models\Guest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RevocationRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Guest $guest,
        public readonly Event $event,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Rücknahme-Anfrage von {$this->guest->firstname} {$this->guest->lastname}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.revocation-request',
        );
    }
}
