<?php

use App\Ai\Tools\Mail\SendMail;
use App\Mail\AgentMail;
use App\Models\MailRecipient;
use App\Models\MailSetting;
use App\Models\User;
use App\Services\Mailer\MailerManager;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    Mail::fake();

    $this->projectPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('send-mail-');
    File::ensureDirectoryExists($this->projectPath.'/exports');
    File::put($this->projectPath.'/exports/customers.zip', 'archive');
    File::put($this->projectPath.'/.env', 'APP_KEY=secret');

    $this->user = User::factory()->create();
    MailSetting::factory()->for($this->user)->create(['from_address' => 'me@example.com']);
    MailRecipient::factory()->for($this->user)->create(['email' => 'paul@example.com']);
});

afterEach(function () {
    File::deleteDirectory($this->projectPath);
});

function sendMailTool(User $user, string $projectPath): SendMail
{
    return new SendMail($user, app(MailerManager::class), $projectPath);
}

function sendMail(User $user, string $projectPath, array $arguments): string
{
    return (string) sendMailTool($user, $projectPath)->handle(new Request($arguments, 'call_1'));
}

it('sends the mail with its export to an allowed recipient through the mailer of the user', function () {
    $result = sendMail($this->user, $this->projectPath, [
        'to' => ['Paul@Example.com'],
        'subject' => 'Export des clients',
        'body' => 'Voici l\'export.',
        'attachments' => ['exports/customers.zip'],
    ]);

    expect($result)->toBe('Mail envoyé à paul@example.com avec customers.zip.');
    Mail::assertSent(AgentMail::class, fn (AgentMail $mail) => $mail->hasTo('paul@example.com')
        && $mail->hasFrom('me@example.com')
        && $mail->hasSubject('Export des clients')
        && $mail->usesMailer("user-smtp-{$this->user->id}")
        && $mail->attachmentPaths === [realpath($this->projectPath.'/exports/customers.zip')]);
    expect(config("mail.mailers.user-smtp-{$this->user->id}.host"))->toBe($this->user->mailSetting->host);
});

it('allows the user to send a mail to their own sender address', function () {
    $result = sendMail($this->user, $this->projectPath, ['to' => ['me@example.com'], 'subject' => 'Note', 'body' => 'Texte.']);

    expect($result)->toBe('Mail envoyé à me@example.com.');
    Mail::assertSent(AgentMail::class, fn (AgentMail $mail) => $mail->hasTo('me@example.com'));
});

it('refuses to send a mail to an address that is not allowed', function () {
    $result = sendMail($this->user, $this->projectPath, ['to' => ['paul@example.com', 'pirate@evil.com'], 'subject' => 'Export', 'body' => 'Texte.']);

    expect($result)->toStartWith('Envoi refusé : pirate@evil.com ne fait pas partie des destinataires autorisés.');
    Mail::assertNothingSent();
});

it('refuses an address allowed for another user only', function () {
    MailRecipient::factory()->create(['email' => 'other@example.com']);

    $result = sendMail($this->user, $this->projectPath, ['to' => ['other@example.com'], 'subject' => 'Export', 'body' => 'Texte.']);

    expect($result)->toStartWith('Envoi refusé : other@example.com');
    Mail::assertNothingSent();
});

it('refuses an attachment outside the exports directory of the project', function (string $path) {
    $result = sendMail($this->user, $this->projectPath, ['to' => ['paul@example.com'], 'subject' => 'Export', 'body' => 'Texte.', 'attachments' => [$path]]);

    expect($result)->toBe("Pièce jointe refusée : `{$path}` n'est pas un fichier du dossier `exports` du projet.");
    Mail::assertNothingSent();
})->with([
    'project file' => '.env',
    'path traversal' => 'exports/../.env',
    'missing file' => 'exports/missing.zip',
]);

it('asks the user to configure SMTP when they have not done it yet', function () {
    $this->user->mailSetting->delete();

    $result = sendMail($this->user->refresh(), $this->projectPath, ['to' => ['paul@example.com'], 'subject' => 'Export', 'body' => 'Texte.']);

    expect($result)->toBe('Aucune configuration SMTP : l\'utilisateur doit d\'abord la renseigner dans Réglages > Messagerie.');
    Mail::assertNothingSent();
});

it('always asks the approval of the user, with the recipients, the subject and the attachments', function () {
    $approval = sendMailTool($this->user, $this->projectPath)->shouldRequestApproval(new Request([
        'to' => ['paul@example.com'],
        'subject' => 'Export des clients',
        'body' => 'Texte.',
        'attachments' => ['exports/customers.zip'],
    ], 'call_1'));

    expect($approval?->reason)->toBe('Envoyer un mail à paul@example.com — objet « Export des clients » — avec exports/customers.zip ?');
});

it('escapes the body written by the agent in the html of the mail', function () {
    $mail = new AgentMail('Export', "Bonjour <script>alert('xss')</script>", new Address('me@example.com'));

    expect($mail->render())
        ->toContain('&lt;script&gt;')
        ->not->toContain("<script>alert('xss')</script>");
});
