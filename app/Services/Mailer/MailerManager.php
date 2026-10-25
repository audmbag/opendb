<?php

declare(strict_types=1);

namespace App\Services\Mailer;

use App\Models\MailSetting;
use App\Models\User;
use App\Services\Mailer\Data\OutgoingMail;
use RuntimeException;

/**
 * Passerelle entre l'application et l'envoi de mails avec la configuration SMTP de l'utilisateur.
 */
final readonly class MailerManager
{
    public function __construct(
        private AgentMailSender $sender,
        private UserMailerFactory $mailerFactory,
    ) {}

    /**
     * Envoie un mail rédigé par l'agent, à des destinataires autorisés uniquement.
     *
     * @param  string  $attachmentDirectory  Dossier, relatif au projet, dont les fichiers peuvent être joints.
     * @return array{to: list<string>, attachments: list<string>} Les destinataires et les noms des pièces jointes envoyées
     *
     * @throws RuntimeException Si la configuration manque, si un destinataire n'est pas autorisé, si une pièce jointe est refusée ou si l'envoi échoue
     */
    public function send(User $user, OutgoingMail $mail, string $projectPath, string $attachmentDirectory): array
    {
        return $this->sender->send($user, $mail, $projectPath, $attachmentDirectory);
    }

    /**
     * Vérifie que le serveur SMTP accepte la connexion et les identifiants.
     *
     * @throws RuntimeException Si la connexion ou l'authentification échoue
     */
    public function testConnection(MailSetting $settings): void
    {
        $this->mailerFactory->testConnection($settings);
    }
}
