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
     * @throws RuntimeException si l'adresse est invalide ou déjà autorisée
     */
    public function add(User $user, string $email, ?string $name = null): MailRecipient
    {
        $email = mb_strtolower(trim($email));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException("L'adresse « {$email} » est invalide.");
        }

        if ($user->mailRecipients()->where('email', $email)->exists()) {
            throw new RuntimeException("L'adresse « {$email} » fait déjà partie des destinataires autorisés.");
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
            throw new RuntimeException("L'adresse « {$email} » ne fait pas partie des destinataires autorisés.");
        }
    }
}
