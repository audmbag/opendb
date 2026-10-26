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

    /**
     * @param  array{to?: mixed, subject?: mixed, body?: mixed, attachments?: mixed}  $values
     */
    public static function fromArray(array $values): self
    {
        return new self(
            to: array_values(array_map(fn (mixed $email): string => mb_strtolower(trim((string) $email)), (array) ($values['to'] ?? []))),
            subject: trim((string) ($values['subject'] ?? '')),
            body: self::withRealLineBreaks((string) ($values['body'] ?? '')),
            attachments: array_values(array_map(strval(...), (array) ($values['attachments'] ?? []))),
        );
    }

    /**
     * Remplace les séquences littérales `\n` (et `\r\n`) qu'un modèle écrit parfois à la place de vrais retours à la ligne.
     */
    public static function withRealLineBreaks(string $text): string
    {
        return str_replace(['\r\n', '\n'], "\n", $text);
    }

    /**
     * @return array{to: list<string>, subject: string, body: string, attachments: list<string>}
     */
    public function toArray(): array
    {
        return [
            'to' => $this->to,
            'subject' => $this->subject,
            'body' => $this->body,
            'attachments' => $this->attachments,
        ];
    }
}
