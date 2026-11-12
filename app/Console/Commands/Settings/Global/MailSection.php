<?php

namespace App\Console\Commands\Settings\Global;

use App\Console\Commands\Settings\UserSection;
use App\Enums\MailEncryptionEnum;
use App\Http\Requests\MailRecipientRequest;
use App\Http\Requests\MailSettingRequest;
use App\Models\MailRecipient;
use App\Models\User;
use App\Repository\MailRecipientRepository;
use App\Repository\MailSettingRepository;
use App\Services\Mailer\MailerManager;
use RuntimeException;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

/**
 * The SMTP server the agent sends mails through, and the only addresses it may send them to.
 */
class MailSection extends UserSection
{
    private const string NO_ENCRYPTION = 'none';

    public function __construct(
        protected MailSettingRepository $mailSettings,
        protected MailRecipientRepository $recipients,
        protected MailerManager $mailer,
    ) {}

    public function label(): string
    {
        return __('settings.nav.mail');
    }

    public function run(User $user): void
    {
        $this->menu(
            label: __('settings.nav.mail'),
            actions: fn (): array => [
                'configure' => __('console.settings.mail.configure'),
                ...($user->mailSetting !== null ? ['test' => __('settings.mail.test_connection')] : []),
                'recipients' => __('settings.mail.recipients'),
            ],
            run: fn (string $action) => match ($action) {
                'configure' => $this->configure($user),
                'test' => $this->test($user),
                'recipients' => $this->manageRecipients($user),
            },
            overview: fn () => $this->showServer($user),
        );
    }

    private function showServer(User $user): void
    {
        $setting = $user->mailSetting;

        if ($setting === null) {
            warning(__('console.settings.mail.not_configured'));

            return;
        }

        table([__('settings.mail.smtp_server'), ''], [
            [__('settings.mail.host'), $setting->host],
            [__('settings.mail.port'), $setting->port],
            [__('settings.mail.encryption'), $setting->encryption !== null ? strtoupper($setting->encryption->value) : __('settings.mail.no_encryption')],
            [__('settings.mail.username'), $setting->username ?? '—'],
            [__('settings.mail.password'), $setting->has_password ? '••••••••' : '—'],
            [__('settings.mail.from_address'), $setting->from_address],
            [__('settings.mail.from_name'), $setting->from_name ?? '—'],
        ]);
    }

    /**
     * An empty password keeps the one already saved.
     */
    private function configure(User $user): void
    {
        $current = $user->mailSetting;

        $encryption = select(
            __('settings.mail.encryption'),
            [self::NO_ENCRYPTION => __('settings.mail.no_encryption'), ...$this->encryptionOptions()],
            default: $current?->encryption?->value ?? self::NO_ENCRYPTION,
        );

        $request = $this->validate(MailSettingRequest::class, [
            'host' => text(__('settings.mail.host'), default: (string) $current?->host, required: true),
            'port' => text(__('settings.mail.port'), default: (string) ($current?->port ?? 587), required: true),
            'encryption' => $encryption === self::NO_ENCRYPTION ? null : $encryption,
            'username' => text(__('settings.mail.username'), default: (string) $current?->username),
            'password' => password(__('settings.mail.password'), hint: $current?->has_password ? __('console.settings.mail.keep_password') : ''),
            'from_address' => text(__('settings.mail.from_address'), placeholder: __('settings.mail.from_address_placeholder'), default: (string) $current?->from_address, required: true),
            'from_name' => text(__('settings.mail.from_name'), default: (string) $current?->from_name),
        ]);

        if ($request === null) {
            return;
        }

        $this->mailSettings->save($user, $request->validated());
        $user->unsetRelation('mailSetting');

        info(__('mail.messages.smtp_saved'));
    }

    /**
     * @return array<string, string>
     */
    private function encryptionOptions(): array
    {
        return collect(MailEncryptionEnum::cases())
            ->mapWithKeys(fn (MailEncryptionEnum $case): array => [$case->value => strtoupper($case->value)])
            ->all();
    }

    private function test(User $user): void
    {
        try {
            spin(fn () => $this->mailer->testConnection($user->mailSetting), __('console.settings.mail.testing'));
        } catch (RuntimeException $exception) {
            error($exception->getMessage());

            return;
        }

        info(__('mail.messages.smtp_connected'));
    }

    private function manageRecipients(User $user): void
    {
        $this->menu(
            label: __('settings.mail.recipients'),
            actions: fn (): array => [
                'add' => __('console.settings.mail.add_recipient'),
                ...($this->recipients->list($user)->isNotEmpty() ? ['remove' => __('console.settings.mail.remove_recipient')] : []),
            ],
            run: fn (string $action) => match ($action) {
                'add' => $this->addRecipient($user),
                'remove' => $this->removeRecipient($user),
            },
            overview: fn () => $this->showRecipients($user),
        );
    }

    private function showRecipients(User $user): void
    {
        $recipients = $this->recipients->list($user);

        if ($recipients->isEmpty()) {
            warning(__('settings.mail.no_recipients'));

            return;
        }

        table(
            [__('console.settings.mail.email'), __('console.settings.mail.name')],
            $recipients->map(fn (MailRecipient $recipient): array => [$recipient->email, $recipient->name ?? '—'])->all(),
        );
    }

    private function addRecipient(User $user): void
    {
        $request = $this->validate(MailRecipientRequest::class, [
            'email' => text(__('console.settings.mail.email'), placeholder: __('settings.mail.recipient_email_placeholder'), required: true),
            'name' => text(__('console.settings.mail.name'), placeholder: __('settings.mail.recipient_name_placeholder')),
        ]);

        if ($request === null) {
            return;
        }

        try {
            $recipient = $this->recipients->add($user, $request->validated('email'), $request->validated('name'));
        } catch (RuntimeException $exception) {
            error($exception->getMessage());

            return;
        }

        info(__('console.settings.mail.recipient_added', ['email' => $recipient->email]));
    }

    private function removeRecipient(User $user): void
    {
        $email = (string) select(
            __('console.settings.mail.remove_recipient'),
            $this->recipients->list($user)->pluck('email', 'email')->all(),
            hint: __('settings.mail.remove_recipient_description'),
        );

        $this->recipients->remove($user, $email);

        info(__('console.settings.mail.recipient_removed', ['email' => $email]));
    }
}
