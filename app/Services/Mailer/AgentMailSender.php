<?php

declare(strict_types=1);

namespace App\Services\Mailer;

use App\Mail\AgentMail;
use App\Models\User;
use App\Services\Mailer\Data\OutgoingMail;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Envoie un mail de l'agent après avoir vérifié la configuration, les destinataires et les pièces jointes.
 */
class AgentMailSender
{
    public function __construct(
        private RecipientGuard $recipientGuard,
        private MailAttachmentResolver $attachmentResolver,
        private UserMailerFactory $mailerFactory,
    ) {}

    /**
     * @return array{to: list<string>, attachments: list<string>}
     *
     * @throws RuntimeException
     */
    public function send(User $user, OutgoingMail $mail, string $projectPath, string $attachmentDirectory): array
    {
        $settings = $user->mailSetting
            ?? throw new RuntimeException('Aucune configuration SMTP : l\'utilisateur doit d\'abord la renseigner dans Réglages > Messagerie.');

        $recipients = $this->recipientGuard->allowedRecipients($user, $settings, $mail->to);
        $attachments = $this->attachmentResolver->resolve($projectPath, $attachmentDirectory, $mail->attachments);

        try {
            Mail::mailer($this->mailerFactory->mailerName($settings))
                ->to($recipients)
                ->send(new AgentMail(
                    mailSubject: $mail->subject,
                    body: $mail->body,
                    sender: new Address($settings->from_address, $settings->from_name),
                    attachmentPaths: $attachments,
                ));
        } catch (TransportExceptionInterface $e) {
            throw new RuntimeException("L'envoi a échoué : {$e->getMessage()}", previous: $e);
        }

        return [
            'to' => $recipients,
            'attachments' => array_map(basename(...), $attachments),
        ];
    }
}
