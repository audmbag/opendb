<?php

use App\Ai\Tools\Mail\SendMail;
use App\Models\MailRecipient;
use App\Models\MailSetting;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Laravel\Ai\Enums\MessageStatus;
use Laravel\Ai\Models\Conversation;

beforeEach(function () {
    $this->projectPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('mail-form-');
    File::ensureDirectoryExists($this->projectPath.'/.opendb/exports');
    File::put($this->projectPath.'/.opendb/exports/customers.zip', 'archive');
    File::put($this->projectPath.'/.env', 'APP_KEY=secret');

    $this->user = User::factory()->create();
    MailSetting::factory()->for($this->user)->create(['from_address' => 'me@example.com']);
    MailRecipient::factory()->for($this->user)->create(['email' => 'paul@example.com']);

    $this->project = Project::factory()->for($this->user)->create(['path' => $this->projectPath]);
    $this->conversation = pendingSendMailConversation($this->user, $this->project);
});

afterEach(function () {
    File::deleteDirectory($this->projectPath);
});

function pendingSendMailConversation(User $user, Project $project, MessageStatus $status = MessageStatus::Paused): Conversation
{
    return conversationWithToolCall($user, $project, [
        'id' => 'call_1',
        'name' => 'SendMail',
        'arguments' => ['to' => ['paul@example.com'], 'subject' => 'Export', 'body' => 'Texte.', 'attachments' => []],
        'approval_reason' => 'Saisie utilisateur requise via formulaire.',
    ], $status);
}

function mailForm(array $overrides = []): array
{
    return [
        'to' => ['Paul@Example.com'],
        'subject' => 'Export des clients',
        'body' => 'Voici l\'export.',
        'attachments' => ['.opendb/exports/customers.zip'],
        ...$overrides,
    ];
}

it('keeps the mail the user edited until the call is approved', function () {
    $response = $this->actingAs($this->user)->postJson("/conversations/{$this->conversation->id}/tool-calls/call_1/mail", mailForm());

    $expected = ['to' => ['paul@example.com'], 'subject' => 'Export des clients', 'body' => 'Voici l\'export.', 'attachments' => ['.opendb/exports/customers.zip']];
    $response->assertOk()->assertExactJson(['data' => $expected]);
    expect(Cache::get(SendMail::submissionCacheKey('call_1')))->toBe($expected);
});

it('turns the literal line breaks written by the agent into real ones', function () {
    $this->actingAs($this->user)->postJson("/conversations/{$this->conversation->id}/tool-calls/call_1/mail", mailForm(['body' => 'Bonjour,\n\nVoici l\'export.\r\nCordialement,']))
        ->assertOk();

    expect(Cache::get(SendMail::submissionCacheKey('call_1'))['body'])->toBe("Bonjour,\n\nVoici l'export.\nCordialement,");
});

it('refuses a recipient that is not allowed', function () {
    $this->actingAs($this->user)->postJson("/conversations/{$this->conversation->id}/tool-calls/call_1/mail", mailForm(['to' => ['pirate@evil.com']]))
        ->assertJsonValidationErrors(['to' => 'Envoi refusé : pirate@evil.com ne fait pas partie des destinataires autorisés (Réglages > Messagerie).']);

    expect(Cache::has(SendMail::submissionCacheKey('call_1')))->toBeFalse();
});

it('refuses an attachment outside the exports directory of the project', function (string $path) {
    $this->actingAs($this->user)->postJson("/conversations/{$this->conversation->id}/tool-calls/call_1/mail", mailForm(['attachments' => [$path]]))
        ->assertJsonValidationErrors(['attachments' => "Pièce jointe refusée : `{$path}` n'est pas un fichier du dossier `.opendb/exports` du projet."]);
})->with([
    'project file' => '.env',
    'path traversal' => '.opendb/exports/../.env',
    'missing file' => '.opendb/exports/missing.zip',
]);

it('asks the user to configure SMTP first', function () {
    $this->user->mailSetting()->delete();

    $this->actingAs($this->user)->postJson("/conversations/{$this->conversation->id}/tool-calls/call_1/mail", mailForm())
        ->assertJsonValidationErrors(['settings' => 'Aucune configuration SMTP : l\'utilisateur doit d\'abord la renseigner dans Réglages > Messagerie.']);
});

it('validates the required fields of the mail', function () {
    $this->actingAs($this->user)->postJson("/conversations/{$this->conversation->id}/tool-calls/call_1/mail", [])
        ->assertJsonValidationErrors(['to', 'subject', 'body']);
});

it('returns 404 for a mail that is no longer waiting for the user', function () {
    $conversation = pendingSendMailConversation($this->user, $this->project, MessageStatus::Completed);

    $this->actingAs($this->user)->postJson("/conversations/{$conversation->id}/tool-calls/call_1/mail", mailForm())->assertNotFound();
});

it('returns 404 for the conversation of another user', function () {
    $this->actingAs(User::factory()->create())->postJson("/conversations/{$this->conversation->id}/tool-calls/call_1/mail", mailForm())->assertNotFound();

    expect(Cache::has(SendMail::submissionCacheKey('call_1')))->toBeFalse();
});

it('describes the attachments the agent proposed, with the reason of a refused one', function () {
    $conversation = conversationWithToolCall($this->user, $this->project, [
        'id' => 'call_2',
        'name' => 'SendMail',
        'arguments' => ['to' => ['paul@example.com'], 'subject' => 'Export', 'body' => 'Texte.', 'attachments' => ['.opendb/exports/customers.zip', '.env']],
        'approval_reason' => 'Saisie utilisateur requise via formulaire.',
    ]);

    $response = $this->actingAs($this->user)->getJson("/conversations/{$conversation->id}/tool-calls/call_2/mail/attachments");

    $response->assertOk()->assertExactJson(['data' => [
        'files' => [
            ['path' => '.opendb/exports/customers.zip', 'name' => 'customers.zip', 'extension' => 'zip', 'size' => 7, 'modified_at' => date(DATE_ATOM, filemtime($this->projectPath.'/.opendb/exports/customers.zip')), 'error' => null],
            ['path' => '.env', 'name' => '.env', 'extension' => 'env', 'size' => null, 'modified_at' => null, 'error' => "Pièce jointe refusée : `.env` n'est pas un fichier du dossier `.opendb/exports` du projet."],
        ],
        'maxTotalBytes' => 20 * 1024 * 1024,
    ]]);
});

it('returns 404 for the attachments of a mail that is no longer waiting for the user', function () {
    $conversation = pendingSendMailConversation($this->user, $this->project, MessageStatus::Completed);

    $this->actingAs($this->user)->getJson("/conversations/{$conversation->id}/tool-calls/call_1/mail/attachments")->assertNotFound();
});
