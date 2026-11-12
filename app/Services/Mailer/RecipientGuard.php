<?php

declare(strict_types=1);

namespace App\Services\Mailer;

use App\Models\MailSetting;
use App\Models\User;
use App\Services\Mailer\Exception\InvalidMailException;

/**
 * N'autorise que les destinataires de la liste de l'utilisateur, plus sa propre adresse d'expéditeur.
 */
class RecipientGuard
{
    /**
     * Nombre maximal de destinataires d'un mail.
     */
    public const int MAX_RECIPIENTS = 10;

    /**
     * @param  list<string>  $to
     * @return list<string> Les adresses, normalisées en minuscules et sans doublon.
     *
     * @throws InvalidMailException si une adresse est invalide ou n'est pas autorisée
     */
    public function allowedRecipients(User $user, MailSetting $settings, array $to): array
    {
        $recipients = $to
            |> (fn (array $x): array => array_map(fn (mixed $email): string => mb_strtolower(trim((string) $email)), $x))
            |> (fn (array $x): array => array_filter($x, fn (string $email): bool => $email !== ''))
            |> array_unique(...)
            |> array_values(...);

        if ($recipients === []) {
            throw InvalidMailException::recipients(__('mail.errors.no_recipient'));
        }

        if (count($recipients) > self::MAX_RECIPIENTS) {
            throw InvalidMailException::recipients(__('mail.errors.too_many_recipients', ['max' => self::MAX_RECIPIENTS]));
        }

        $invalid = array_filter($recipients, fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) === false);

        if ($invalid !== []) {
            throw InvalidMailException::recipients(__('mail.errors.invalid_addresses', ['addresses' => implode(', ', $invalid)]));
        }

        $allowed = $user->mailRecipients()->pluck('email')
            ->push($settings->from_address)
            ->map(fn (string $email): string => mb_strtolower($email))
            ->all();

        $refused = array_diff($recipients, $allowed);

        if ($refused !== []) {
            throw InvalidMailException::recipients(__('mail.errors.recipients_refused', ['recipients' => implode(', ', $refused)]));
        }

        return $recipients;
    }
}
