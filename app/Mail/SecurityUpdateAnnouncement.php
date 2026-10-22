<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class SecurityUpdateAnnouncement extends Mailable implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [60, 120, 300, 600];

    public function __construct(public string $recipientName) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address('crewcompass@gmail.com', 'Dave')],
            subject: 'K4 Parser security update and Flight Plan Brief',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.security-update-announcement',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
