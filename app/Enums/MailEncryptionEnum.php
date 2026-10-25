<?php

namespace App\Enums;

enum MailEncryptionEnum: string
{
    case Tls = 'tls';

    case Ssl = 'ssl';

    /**
     * Le schéma du DSN SMTP : `smtps` pour une connexion chiffrée dès l'ouverture (SSL),
     * `smtp` sinon, Symfony Mailer passant alors en STARTTLS quand le serveur le propose.
     */
    public function scheme(): string
    {
        return match ($this) {
            self::Ssl => 'smtps',
            self::Tls => 'smtp',
        };
    }
}
