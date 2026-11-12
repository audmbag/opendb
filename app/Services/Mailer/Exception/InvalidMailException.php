<?php

namespace App\Services\Mailer\Exception;

use RuntimeException;

/**
 * Mail refusé avant l'envoi, avec le champ du formulaire de mail qui en est la cause.
 */
class InvalidMailException extends RuntimeException
{
    public function __construct(string $message, public readonly string $field)
    {
        parent::__construct($message);
    }

    public static function missingSettings(): self
    {
        return new self(__('mail.errors.missing_settings'), 'settings');
    }

    public static function recipients(string $message): self
    {
        return new self($message, 'to');
    }

    public static function attachments(string $message): self
    {
        return new self($message, 'attachments');
    }
}
