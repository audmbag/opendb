<?php

declare(strict_types=1);

namespace App\Services\Mailer\Data;

/**
 * Mail rédigé par l'agent, avant la vérification de ses destinataires et de ses pièces jointes.
 */
final readonly class OutgoingMail
{
    /**
     * @param  list<string>  $to  Adresses des destinataires.
     * @param  list<string>  $attachments  Chemins des pièces jointes, relatifs au dossier du projet.
     */
    public function __construct(
        public array $to,
        public string $subject,
        public string $body,
        public array $attachments = [],
    ) {}
}
