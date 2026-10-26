<?php

use App\Ai\Tools\Mail\SendMail;
use App\Mail\AgentMail;
use App\Models\MailRecipient;
use App\Models\MailSetting;
use App\Models\User;
use App\Services\Mailer\MailerManager;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    Mail::fake();

    $this->projectPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('send-mail-');
    File::ensureDirectoryExists($this->projectPath.'/exports');
    File::put($this->projectPath.'/exports/customers.zip', 'archive');

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

/**
 * The arguments the agent proposed: the user edits them in the form before the mail is sent.
 */
function proposedMail(): array
{
    return ['to' => ['paul@example.com'], 'subject' => 'Proposé', 'body' => 'Texte proposé.', 'attachments' => []];
}

it('sends the mail the user submitted in the form, not the one the agent proposed', function () {
    Cache::put(SendMail::submissionCacheKey('call_1'), [
        'to' => ['me@example.com'],
        'subject' => 'Export des clients',
        'body' => 'Voici l\'export.',
        'attachments' => ['exports/customers.zip'],
    ]);

    $result = (string) sendMailTool($this->user, $this->projectPath)->handle(new Request(proposedMail(), 'call_1'));

    expect($result)->toBe(implode("\n", [
        'Mail envoyé. Voici ce qui a réellement été envoyé :',
        '- Destinataires : me@example.com',
        '- Objet : « Export des clients »',
        '- Pièces jointes : customers.zip',
        'L\'utilisateur a modifié ta proposition dans le formulaire avant l\'envoi :',
        '- destinataires : tu avais proposé paul@example.com, le mail est parti à me@example.com',
        '- objet : tu avais proposé « Proposé », l\'objet envoyé est « Export des clients »',
        '- message : l\'utilisateur a réécrit le corps du mail',
        'Rapporte uniquement le mail réellement envoyé ci-dessus : ne mentionne plus les valeurs de ta proposition.',
    ]))->and(Cache::has(SendMail::submissionCacheKey('call_1')))->toBeFalse();
    Mail::assertSent(AgentMail::class, fn (AgentMail $mail) => $mail->hasTo('me@example.com')
        && ! $mail->hasTo('paul@example.com')
        && $mail->hasFrom('me@example.com')
        && $mail->hasSubject('Export des clients')
        && $mail->body === 'Voici l\'export.'
        && $mail->usesMailer("user-smtp-{$this->user->id}")
        && $mail->attachmentPaths === [realpath($this->projectPath.'/exports/customers.zip')]);
});

it('tells the agent when the user sent its proposal unchanged, or left out an attachment', function (array $attachments, string $lastLine) {
    $proposal = [...proposedMail(), 'body' => 'Bonjour,\n\nVoici l\'export.', 'attachments' => ['exports/customers.zip']];
    Cache::put(SendMail::submissionCacheKey('call_1'), [...$proposal, 'body' => "Bonjour,\n\nVoici l'export.", 'attachments' => $attachments]);

    $result = (string) sendMailTool($this->user, $this->projectPath)->handle(new Request($proposal, 'call_1'));

    expect($result)->toEndWith($lastLine);
})->with([
    'unchanged' => [['exports/customers.zip'], "- Pièces jointes : customers.zip\nL'utilisateur a envoyé ta proposition sans la modifier."],
    'attachment left out' => [[], "- pièces jointes non envoyées : exports/customers.zip\nRapporte uniquement le mail réellement envoyé ci-dessus : ne mentionne plus les valeurs de ta proposition."],
]);

it('sends nothing when the user did not submit the form of the call', function () {
    Cache::put(SendMail::submissionCacheKey('call_2'), proposedMail());

    $result = (string) sendMailTool($this->user, $this->projectPath)->handle(new Request(proposedMail(), 'call_1'));

    expect($result)->toBe("L'utilisateur n'a pas soumis le formulaire : le mail n'a pas été envoyé.");
    Mail::assertNothingSent();
});

it('still refuses a recipient that is no longer allowed when the mail is sent', function () {
    Cache::put(SendMail::submissionCacheKey('call_1'), proposedMail());
    $this->user->mailRecipients()->delete();

    $result = (string) sendMailTool($this->user, $this->projectPath)->handle(new Request(proposedMail(), 'call_1'));

    expect($result)->toStartWith('Envoi refusé : paul@example.com ne fait pas partie des destinataires autorisés');
    Mail::assertNothingSent();
});

it('always asks the user to fill the mail form', function () {
    $approval = sendMailTool($this->user, $this->projectPath)->shouldRequestApproval(new Request(proposedMail(), 'call_1'));

    expect($approval?->reason)->toBe('Saisie utilisateur requise via formulaire.');
});

it('escapes the body of the mail in its html', function () {
    $mail = new AgentMail('Export', "Bonjour <script>alert('xss')</script>", new Address('me@example.com'));

    expect($mail->render())
        ->toContain('&lt;script&gt;')
        ->not->toContain("<script>alert('xss')</script>");
});
