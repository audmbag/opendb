<?php

use App\Ai\Tools\Mail\SendMail;
use App\Mail\AgentMail;
use App\Models\MailRecipient;
use App\Models\MailSetting;
use App\Models\Project;
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

    $this->user = User::factory()->create();
    $this->exports = '.opendb/users/'.$this->user->id.'/exports';
    File::ensureDirectoryExists($this->projectPath.'/'.$this->exports);
    File::put($this->projectPath.'/'.$this->exports.'/customers.zip', 'archive');
    MailSetting::factory()->for($this->user)->create(['from_address' => 'me@example.com']);
    MailRecipient::factory()->for($this->user)->create(['email' => 'paul@example.com']);
});

afterEach(function () {
    File::deleteDirectory($this->projectPath);
});

function sendMailTool(User $user, string $projectPath): SendMail
{
    return new SendMail($user, app(MailerManager::class), Project::factory()->for($user)->make(['path' => $projectPath])->workspaceOf($user));
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
        'attachments' => [$this->exports.'/customers.zip'],
    ]);

    $result = (string) sendMailTool($this->user, $this->projectPath)->handle(new Request(proposedMail(), 'call_1'));

    expect($result)->toBe(implode("\n", [
        'Mail sent. Here is what was really sent:',
        '- Recipients: me@example.com',
        '- Subject: "Export des clients"',
        '- Attachments: customers.zip',
        'The user changed your proposal in the form before sending it:',
        '- recipients: you had proposed paul@example.com, the mail was sent to me@example.com',
        '- subject: you had proposed "Proposé", the sent subject is "Export des clients"',
        '- message: the user rewrote the body of the mail',
        'Only report the mail really sent above: do not mention the values of your proposal anymore.',
    ]))->and(Cache::has(SendMail::submissionCacheKey('call_1')))->toBeFalse();
    Mail::assertSent(AgentMail::class, fn (AgentMail $mail) => $mail->hasTo('me@example.com')
        && ! $mail->hasTo('paul@example.com')
        && $mail->hasFrom('me@example.com')
        && $mail->hasSubject('Export des clients')
        && $mail->body === 'Voici l\'export.'
        && $mail->usesMailer("user-smtp-{$this->user->id}")
        && $mail->attachmentPaths === [realpath($this->projectPath.'/'.$this->exports.'/customers.zip')]);
});

it('tells the agent when the user sent its proposal unchanged, or left out an attachment', function (array $attachments, string $lastLine) {
    $proposal = [...proposedMail(), 'body' => 'Bonjour,\n\nVoici l\'export.', 'attachments' => [$this->exports.'/customers.zip']];
    $attachments = array_map(fn (string $file): string => $this->exports.'/'.$file, $attachments);
    Cache::put(SendMail::submissionCacheKey('call_1'), [...$proposal, 'body' => "Bonjour,\n\nVoici l'export.", 'attachments' => $attachments]);

    $result = (string) sendMailTool($this->user, $this->projectPath)->handle(new Request($proposal, 'call_1'));

    expect($result)->toEndWith(str_replace(':exports', $this->exports, $lastLine));
})->with([
    'unchanged' => [['customers.zip'], "- Attachments: customers.zip\nThe user sent your proposal without changing it."],
    'attachment left out' => [[], "- attachments not sent: :exports/customers.zip\nOnly report the mail really sent above: do not mention the values of your proposal anymore."],
]);

it('sends nothing when the user did not submit the form of the call', function () {
    Cache::put(SendMail::submissionCacheKey('call_2'), proposedMail());

    $result = (string) sendMailTool($this->user, $this->projectPath)->handle(new Request(proposedMail(), 'call_1'));

    expect($result)->toBe('The user did not submit the form: the mail was not sent.');
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

    expect(json_decode($approval->reason, true))->toBe(['key' => 'ai.approvals.form_input', 'params' => []]);
});

it('escapes the body of the mail in its html', function () {
    $mail = new AgentMail('Export', "Bonjour <script>alert('xss')</script>", new Address('me@example.com'));

    expect($mail->render())
        ->toContain('&lt;script&gt;')
        ->not->toContain("<script>alert('xss')</script>");
});
