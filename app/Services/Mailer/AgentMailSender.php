<?php

declare(strict_types=1);

namespace App\Services\Mailer;

use App\Mail\AgentMail;
use App\Models\MailSetting;
use App\Models\User;
use App\Services\Mailer\Data\OutgoingMail;
use App\Services\Mailer\Exception\InvalidMailException;
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
     * Vérifie le mail sans l'envoyer et le renvoie avec ses destinataires normalisés.
     *
     * @throws InvalidMailException
     */
    public function validate(User $user, OutgoingMail $mail, string $projectPath, string $attachmentDirectory): OutgoingMail
    {
        [, $recipients] = $this->checked($user, $mail, $projectPath, $attachmentDirectory);

        return new OutgoingMail($recipients, $mail->subject, $mail->body, array_values(array_unique($mail->attachments)));
    }

    /**
     * @return array{to: list<string>, attachments: list<string>}
     *
     * @throws RuntimeException
     */
    public function send(User $user, OutgoingMail $mail, string $projectPath, string $attachmentDirectory): array
    {
        [$settings, $recipients, $attachments] = $this->checked($user, $mail, $projectPath, $attachmentDirectory);

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

    /**
     * @return array{0: MailSetting, 1: list<string>, 2: list<string>} La configuration, les destinataires normalisés et les chemins absolus des pièces jointes
     *
     * @throws InvalidMailException
     */
    protected function checked(User $user, OutgoingMail $mail, string $projectPath, string $attachmentDirectory): array
    {
        $settings = $user->mailSetting ?? throw InvalidMailException::missingSettings();

        return [
            $settings,
            $this->recipientGuard->allowedRecipients($user, $settings, $mail->to),
            $this->attachmentResolver->resolve($projectPath, $attachmentDirectory, $mail->attachments),
        ];
    }
}
