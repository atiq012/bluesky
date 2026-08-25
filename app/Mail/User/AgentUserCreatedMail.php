<?php

namespace App\Mail\User;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AgentUserCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $userName,
        public string $agencyName,
        public string $username,
        public string $phone,
        public string $department,
        public string $designation,
        public string $defaultPassword,
        public string $portalUrl,
        public string $createdByName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your B2B Portal Account Has Been Created',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.user.created',
        );
    }
}
