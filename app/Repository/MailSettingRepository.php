<?php

namespace App\Repository;

use App\Models\MailSetting;
use App\Models\User;

class MailSettingRepository
{
    /**
     * Enregistre la configuration SMTP de l'utilisateur. Un mot de passe vide conserve celui déjà enregistré.
     *
     * @param  array{host: string, port: int, encryption?: string|null, username?: string|null, password?: string|null, from_address: string, from_name?: string|null}  $data
     */
    public function save(User $user, array $data): MailSetting
    {
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        return $user->mailSetting()->updateOrCreate([], $data);
    }
}
