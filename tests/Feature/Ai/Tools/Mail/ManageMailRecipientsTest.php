<?php

use App\Ai\Tools\Mail\ManageMailRecipients;
use App\Models\MailRecipient;
use App\Models\MailSetting;
use App\Models\User;
use App\Repository\MailRecipientRepository;
use App\Support\TranslatableMessage;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->user = User::factory()->create();
});

function recipientsTool(User $user): ManageMailRecipients
{
    return new ManageMailRecipients($user, app(MailRecipientRepository::class));
}

function manageRecipients(User $user, array $arguments): string
{
    return (string) recipientsTool($user)->handle(new Request($arguments, 'call_1'));
}

it('lists the allowed recipients of the user with their sender address', function () {
    MailSetting::factory()->for($this->user)->create(['from_address' => 'me@example.com']);
    MailRecipient::factory()->for($this->user)->create(['email' => 'paul@example.com', 'name' => 'Paul']);
    MailRecipient::factory()->create(['email' => 'other@example.com']);

    expect(manageRecipients($this->user, ['action' => 'list']))->toBe("Allowed recipients:\n"
        ."- me@example.com (sender address of the user, always allowed)\n"
        .'- paul@example.com (Paul)');
});

it('says when no recipient is allowed', function () {
    expect(manageRecipients($this->user, ['action' => 'list']))->toBe('No allowed recipient.');
});

it('adds a recipient in lower case', function () {
    $result = manageRecipients($this->user, ['action' => 'add', 'email' => ' Paul@Example.com ', 'name' => 'Paul']);

    expect($result)->toBe('"paul@example.com" is now one of the allowed recipients.')
        ->and($this->user->mailRecipients()->sole()->only('email', 'name'))->toBe(['email' => 'paul@example.com', 'name' => 'Paul']);
});

it('refuses to add an invalid or already allowed address', function (string $email, string $message) {
    MailRecipient::factory()->for($this->user)->create(['email' => 'paul@example.com']);

    expect(manageRecipients($this->user, ['action' => 'add', 'email' => $email]))->toBe($message)
        ->and($this->user->mailRecipients()->count())->toBe(1);
})->with([
    'invalid' => ['pas-une-adresse', 'L\'adresse « pas-une-adresse » est invalide.'],
    'duplicate' => ['PAUL@example.com', 'L\'adresse « paul@example.com » fait déjà partie des destinataires autorisés.'],
]);

it('removes a recipient of the user only', function () {
    MailRecipient::factory()->for($this->user)->create(['email' => 'paul@example.com']);
    $otherRecipient = MailRecipient::factory()->create(['email' => 'paul@example.com']);

    $result = manageRecipients($this->user, ['action' => 'remove', 'email' => 'paul@example.com']);

    expect($result)->toBe('"paul@example.com" is no longer one of the allowed recipients.')
        ->and($this->user->mailRecipients()->exists())->toBeFalse()
        ->and($otherRecipient->fresh())->not->toBeNull();
});

it('says when the address to remove is not allowed', function () {
    expect(manageRecipients($this->user, ['action' => 'remove', 'email' => 'paul@example.com']))
        ->toBe('L\'adresse « paul@example.com » ne fait pas partie des destinataires autorisés.');
});

it('rejects invalid parameters', function (array $arguments, string $message) {
    expect(manageRecipients($this->user, $arguments))->toBe($message);
})->with([
    'add without email' => [['action' => 'add', 'email' => ' '], 'The `email` parameter is required for the `add` action.'],
    'unknown action' => [['action' => 'purge'], 'Unknown action: use `list`, `add` or `remove`.'],
]);

it('asks the approval of the user only to change the list of recipients', function (array $arguments, ?string $reason) {
    MailSetting::factory()->for($this->user)->create(['from_address' => 'me@example.com']);
    MailRecipient::factory()->for($this->user)->create(['email' => 'paul@example.com']);

    $approval = recipientsTool($this->user)->shouldRequestApproval(new Request($arguments, 'call_1'));

    expect($reason === null ? $approval : TranslatableMessage::text(json_decode($approval->reason, true)))->toBe($reason);
})->with([
    'add a new address' => [['action' => 'add', 'email' => 'Jean@Example.com'], 'Autoriser l\'envoi de mails à « jean@example.com » ?'],
    'remove a listed address' => [['action' => 'remove', 'email' => 'paul@example.com'], 'Retirer « paul@example.com » des destinataires autorisés ?'],
    'add an address already listed' => [['action' => 'add', 'email' => 'PAUL@example.com'], null],
    'add the sender address' => [['action' => 'add', 'email' => 'me@example.com'], null],
    'add an invalid address' => [['action' => 'add', 'email' => 'pas-une-adresse'], null],
    'remove an address not listed' => [['action' => 'remove', 'email' => 'jean@example.com'], null],
    'list' => [['action' => 'list'], null],
]);

it('answers directly that the sender address is already allowed', function () {
    MailSetting::factory()->for($this->user)->create(['from_address' => 'me@example.com']);

    expect(manageRecipients($this->user, ['action' => 'add', 'email' => 'me@example.com']))
        ->toBe('L\'adresse « me@example.com » fait déjà partie des destinataires autorisés.')
        ->and($this->user->mailRecipients()->exists())->toBeFalse();
});
