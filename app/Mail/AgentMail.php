<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Mail rédigé par l'agent et envoyé avec la configuration SMTP de l'utilisateur.
 */
class AgentMail extends Mailable
{
    /**
     * @param  list<string>  $attachmentPaths  Chemins absolus des pièces jointes, déjà vérifiés.
     */
    public function __construct(
        public string $mailSubject,
        public string $body,
        public Address $sender,
        public array $attachmentPaths = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->sender,
            subject: $this->mailSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.agent-mail',
            text: 'mail.agent-mail-text',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return array_map(fn (string $path): Attachment => Attachment::fromPath($path), $this->attachmentPaths);
    }
}
