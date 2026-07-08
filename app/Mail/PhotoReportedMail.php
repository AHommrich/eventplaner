<?php

namespace App\Mail;

use App\Models\Event;
use App\Models\PhotoReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Notifies the event owner about a new photo report. Reporter identity is
 * intentionally omitted from subject and body — App-Store-Guideline-1.2
 * reviewers expect reporter anonymity so guests are not deterred from
 * flagging content.
 */
class PhotoReportedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly PhotoReport $report,
        public readonly Event $event,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Foto-Meldung für {$this->event->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.photo-reported',
        );
    }
}
