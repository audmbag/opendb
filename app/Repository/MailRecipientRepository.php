<?php

namespace App\Repository;

use App\Models\MailRecipient;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

class MailRecipientRepository
{
    /**
     * @return Collection<int, MailRecipient>
     */
    public function list(User $user): Collection
    {
        return $user->mailRecipients()->orderBy('email')->get();
    }

    /**
     * Indique si l'utilisateur peut déjà envoyer un mail à cette adresse : un destinataire de sa liste ou sa propre adresse d'expéditeur.
     */
    public function isAllowed(User $user, string $email): bool
    {
        $email = mb_strtolower(trim($email));

        return mb_strtolower((string) $user->mailSetting?->from_address) === $email
            || $user->mailRecipients()->where('email', $email)->exists();
    }

    /**
     * Indique si l'adresse fait partie de la liste des destinataires de l'utilisateur.
     */
    public function isListed(User $user, string $email): bool
    {
        return $user->mailRecipients()->where('email', mb_strtolower(trim($email)))->exists();
    }

    /**
     * @throws RuntimeException si l'adresse est invalide ou déjà autorisée
     */
    public function add(User $user, string $email, ?string $name = null): MailRecipient
    {
        $email = mb_strtolower(trim($email));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException(__('mail.errors.invalid_address', ['email' => $email]));
        }

        if ($this->isAllowed($user, $email)) {
            throw new RuntimeException(__('mail.errors.already_allowed', ['email' => $email]));
        }

        return $user->mailRecipients()->create(['email' => $email, 'name' => filled($name) ? trim($name) : null]);
    }

    /**
     * @throws RuntimeException si l'adresse ne fait pas partie des destinataires autorisés
     */
    public function remove(User $user, string $email): void
    {
        $email = mb_strtolower(trim($email));

        if ($user->mailRecipients()->where('email', $email)->delete() === 0) {
            throw new RuntimeException(__('mail.errors.not_allowed', ['email' => $email]));
        }
    }
}
