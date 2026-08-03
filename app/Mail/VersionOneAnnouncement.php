<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VersionOneAnnouncement extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public User $user) {}

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
