<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class VersionOneAnnouncement extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $recipientName) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [
                new Address('crewcompass@gmail.com', 'Dave'),
            ],
            subject: 'K4 Parser v1 is live — turn your JCA schedule into calendar events',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.version-one-announcement',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
