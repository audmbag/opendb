<?php

use App\Ai\Tools\Mail\ManageMailRecipients;
use App\Models\MailRecipient;
use App\Models\MailSetting;
use App\Models\User;
use App\Repository\MailRecipientRepository;
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

    expect(manageRecipients($this->user, ['action' => 'list']))->toBe("Destinataires autorisés :\n"
        ."- me@example.com (adresse d'expéditeur de l'utilisateur, toujours autorisée)\n"
        .'- paul@example.com (Paul)');
});

it('says when no recipient is allowed', function () {
    expect(manageRecipients($this->user, ['action' => 'list']))->toBe('Aucun destinataire autorisé.');
});

it('adds a recipient in lower case', function () {
    $result = manageRecipients($this->user, ['action' => 'add', 'email' => ' Paul@Example.com ', 'name' => 'Paul']);

    expect($result)->toBe('« paul@example.com » fait maintenant partie des destinataires autorisés.')
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

    expect($result)->toBe('« paul@example.com » ne fait plus partie des destinataires autorisés.')
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
    'add without email' => [['action' => 'add', 'email' => ' '], 'Le paramètre `email` est obligatoire pour l\'action `add`.'],
    'unknown action' => [['action' => 'purge'], 'Action inconnue : utilise `list`, `add` ou `remove`.'],
]);

it('asks the approval of the user to add or remove a recipient but not to list them', function (string $action, ?string $reason) {
    $approval = recipientsTool($this->user)->shouldRequestApproval(new Request(['action' => $action, 'email' => 'paul@example.com'], 'call_1'));

    expect($reason === null ? $approval : $approval?->reason)->toBe($reason);
})->with([
    'add' => ['add', 'Autoriser l\'envoi de mails à « paul@example.com » ?'],
    'remove' => ['remove', 'Retirer « paul@example.com » des destinataires autorisés ?'],
    'list' => ['list', null],
]);
