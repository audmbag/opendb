<?php

declare(strict_types=1);

namespace App\Services\Mailer;

use App\Models\MailSetting;
use App\Models\User;
use App\Services\Mailer\Data\OutgoingMail;
use App\Services\Mailer\Exception\InvalidMailException;
use RuntimeException;

/**
 * Passerelle entre l'application et l'envoi de mails avec la configuration SMTP de l'utilisateur.
 */
final readonly class MailerManager
{
    public function __construct(
        private AgentMailSender $sender,
        private UserMailerFactory $mailerFactory,
        private MailAttachmentResolver $attachmentResolver,
    ) {}

    /**
     * Vérifie un mail avant l'envoi (configuration, destinataires autorisés, pièces jointes) et le renvoie normalisé.
     *
     * @param  string  $attachmentDirectory  Dossier, relatif au projet, dont les fichiers peuvent être joints.
     *
     * @throws InvalidMailException Avec le champ du formulaire de mail en cause
     */
    public function validate(User $user, OutgoingMail $mail, string $projectPath, string $attachmentDirectory): OutgoingMail
    {
        return $this->sender->validate($user, $mail, $projectPath, $attachmentDirectory);
    }

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
     * Décrit les pièces jointes proposées (nom, extension, taille, date) et signale celles qui seraient refusées.
     *
     * @param  list<string>  $paths  Chemins relatifs au dossier du projet.
     * @return list<array{path: string, name: string, extension: string, size: int|null, modified_at: string|null, error: string|null}>
     */
    public function describeAttachments(string $projectPath, string $attachmentDirectory, array $paths): array
    {
        return $this->attachmentResolver->describe($projectPath, $attachmentDirectory, $paths);
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
