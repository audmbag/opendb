<?php

declare(strict_types=1);

namespace App\Services\Mailer;

use App\Models\MailSetting;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;

/**
 * Construit un mailer SMTP à partir de la configuration enregistrée par l'utilisateur.
 */
class UserMailerFactory
{
    /**
     * Enregistre le mailer de l'utilisateur dans la configuration et renvoie son nom, pour `Mail::mailer()`.
     */
    public function mailerName(MailSetting $settings): string
    {
        $name = "user-smtp-{$settings->user_id}";

        config(["mail.mailers.{$name}" => $this->config($settings)]);
        Mail::purge($name);

        return $name;
    }

    /**
     * Ouvre une connexion au serveur SMTP pour vérifier la configuration, sans envoyer de mail.
     *
     * @throws RuntimeException si la connexion ou l'authentification échoue
     */
    public function testConnection(MailSetting $settings): void
    {
        $transport = Mail::build($this->config($settings))->getSymfonyTransport();

        try {
            if ($transport instanceof SmtpTransport) {
                $transport->start();
                $transport->stop();
            }
        } catch (TransportExceptionInterface $e) {
            throw new RuntimeException($e->getMessage(), previous: $e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function config(MailSetting $settings): array
    {
        return [
            'transport' => 'smtp',
            'scheme' => $settings->encryption?->scheme(),
            'host' => $settings->host,
            'port' => $settings->port,
            'username' => $settings->username,
            'password' => $settings->password,
            'timeout' => 10,
        ];
    }
}
